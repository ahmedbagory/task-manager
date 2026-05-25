const path = require('path');
require('dotenv').config({ path: path.resolve(__dirname, '..', '.env') });

const fs = require('fs');
const http = require('http');
const crypto = require('crypto');
const axios = require('axios');
const pino = require('pino');
const qrcode = require('qrcode-terminal');
const QRCode = require('qrcode');
const {
  default: makeWASocket,
  useMultiFileAuthState,
  fetchLatestBaileysVersion,
  DisconnectReason,
  downloadMediaMessage,
} = require('@whiskeysockets/baileys');

const logger = pino({
  level: process.env.LOG_LEVEL || 'info',
});

const WEBHOOK_URL = process.env.LARAVEL_WEBHOOK_URL || 'http://127.0.0.1:8000/webhooks/inbound-message';
const HEARTBEAT_URL = process.env.LARAVEL_HEARTBEAT_URL || 'http://127.0.0.1:8000/webhooks/bridge/heartbeat';
const OUTBOUND_PULL_URL = process.env.LARAVEL_OUTBOUND_PULL_URL || deriveOutboundUrl();
const PUBLIC_BASE_URL = process.env.LARAVEL_PUBLIC_URL || derivePublicBaseUrl(WEBHOOK_URL);
const BRIDGE_SECRET = (process.env.BRIDGE_SECRET || '').trim();
const CONFIGURED_GROUP_ID = normalizeGroupId(process.env.GROUP_ID || '');
const CONFIGURED_GROUP_NAME = normalizeText(process.env.GROUP_NAME || '');
const IGNORE_OLD_MESSAGES = toBool(process.env.IGNORE_OLD_MESSAGES, true);
const HEARTBEAT_INTERVAL_SECONDS = Math.max(Number(process.env.HEARTBEAT_INTERVAL_SECONDS || 30) || 30, 5);
const OUTBOUND_POLL_SECONDS = Math.max(Number(process.env.OUTBOUND_POLL_SECONDS || 5) || 5, 3);

const API_PORT = Math.max(Number(process.env.API_PORT || 3001) || 3001, 1024);

const BRIDGE_PROVIDER = 'whatsapp_web_bridge';
const startupUnix = Math.floor(Date.now() / 1000);
const authDir = path.resolve(__dirname, '..', 'auth');
const projectRootDir = path.resolve(__dirname, '..', '..');
const mediaRootDir = path.resolve(projectRootDir, 'storage', 'app', 'public', 'whatsapp-media');
const maxMediaSizeBytes = 20 * 1024 * 1024;
const allowedMediaMimeTypes = new Set([
  'image/jpeg',
  'image/png',
  'image/webp',
  'image/gif',
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'audio/mpeg',
  'audio/ogg',
  'audio/webm',
  'audio/mp4',
  'video/mp4',
  'video/webm',
]);
const mediaDirectories = {
  image: 'images',
  document: 'documents',
  audio: 'audio',
  video: 'videos',
  sticker: 'stickers',
};

const httpClient = axios.create({
  timeout: 10000,
});

let socket = null;
let heartbeatTimer = null;
let outboundTimer = null;
let groupsById = new Map();
let activeGroupId = CONFIGURED_GROUP_ID;
let activeGroupName = CONFIGURED_GROUP_NAME;
let latestQrDataUrl = null;
let connectionState = 'disconnected'; // 'disconnected' | 'qr_pending' | 'connected'

function ensureMediaDirectoriesExist() {
  const directories = new Set([
    mediaRootDir,
    ...Object.values(mediaDirectories).map((directory) => path.resolve(mediaRootDir, directory)),
  ]);

  for (const directory of directories) {
    fs.mkdirSync(directory, { recursive: true });
  }
}

function deriveOutboundUrl() {
  const base = (process.env.LARAVEL_HEARTBEAT_URL || 'http://127.0.0.1:8000/webhooks/bridge/heartbeat')
    .replace(/\/heartbeat\/?$/, '');
  return base + '/outbound';
}

function derivePublicBaseUrl(webhookUrl) {
  try {
    const url = new URL(webhookUrl);
    return `${url.protocol}//${url.host}`;
  } catch (_error) {
    return 'http://127.0.0.1:8000';
  }
}

function toBool(value, fallback) {
  if (value === undefined || value === null || value === '') {
    return fallback;
  }

  return ['1', 'true', 'yes', 'on'].includes(String(value).trim().toLowerCase());
}

function normalizeText(value) {
  const text = String(value || '').trim();

  return text === '' ? null : text;
}

function normalizeGroupId(value) {
  const text = normalizeText(value);

  return text ? text.toLowerCase() : null;
}

function isGroupJid(jid) {
  if (!jid) return false;
  const s = String(jid);
  return s.endsWith('@g.us') || s.startsWith('120363');
}

function isBroadcastJid(jid) {
  if (!jid) return false;
  const s = String(jid);
  return s.endsWith('@broadcast') || s.endsWith('@newsletter') || s === 'status@broadcast';
}

function isValidPhone(value) {
  if (!value) return false;
  const digits = String(value).replace(/\D/g, '');
  if (digits.length < 8 || digits.length > 15) return false;
  if (digits.startsWith('120363')) return false;
  return true;
}

function extractPhoneFromJid(jid) {
  const raw = normalizeText(jid);
  if (!raw) return null;
  if (isGroupJid(raw) || isBroadcastJid(raw)) return null;

  const withoutDomain = raw.split('@')[0] || raw;
  const withoutDevice = withoutDomain.split(':')[0] || withoutDomain;
  const cleaned = withoutDevice.trim().replace(/^\+/, '');

  if (!isValidPhone(cleaned)) return null;
  return cleaned;
}

function normalizePhoneFromJid(jid) {
  return extractPhoneFromJid(jid);
}

function normalizeIncomingPhone(value) {
  const text = normalizeText(value);
  if (!text) return null;
  const cleaned = text.replace(/^\+/, '');
  if (!isValidPhone(cleaned)) return null;
  return cleaned;
}

function toPlainObject(value) {
  try {
    return JSON.parse(JSON.stringify(value || {}));
  } catch (_error) {
    return {};
  }
}

function resolveMessageContainer(content) {
  if (!content || typeof content !== 'object') {
    return null;
  }

  let current = content;

  while (current) {
    if (current.ephemeralMessage && current.ephemeralMessage.message) {
      current = current.ephemeralMessage.message;
      continue;
    }

    if (current.viewOnceMessage && current.viewOnceMessage.message) {
      current = current.viewOnceMessage.message;
      continue;
    }

    if (current.viewOnceMessageV2 && current.viewOnceMessageV2.message) {
      current = current.viewOnceMessageV2.message;
      continue;
    }

    if (current.viewOnceMessageV2Extension && current.viewOnceMessageV2Extension.message) {
      current = current.viewOnceMessageV2Extension.message;
      continue;
    }

    break;
  }

  return current;
}

function extractMessageType(content) {
  if (!content || typeof content !== 'object') {
    return 'unknown';
  }

  const type = Object.keys(content)[0];

  if (!type) {
    return 'unknown';
  }

  if (type === 'conversation' || type === 'extendedTextMessage') {
    return 'text';
  }

  return normalizeMessageType(type);
}

function normalizeMessageType(type) {
  switch (type) {
    case 'conversation':
    case 'extendedTextMessage':
      return 'text';
    case 'imageMessage':
      return 'image';
    case 'documentMessage':
      return 'document';
    case 'audioMessage':
      return 'audio';
    case 'videoMessage':
      return 'video';
    case 'stickerMessage':
      return 'sticker';
    default:
      return type;
  }
}

function extractTextBody(content) {
  if (!content || typeof content !== 'object') {
    return null;
  }

  const candidates = [
    content.conversation,
    content.extendedTextMessage && content.extendedTextMessage.text,
    content.imageMessage && content.imageMessage.caption,
    content.videoMessage && content.videoMessage.caption,
    content.documentMessage && content.documentMessage.caption,
    content.buttonsResponseMessage && content.buttonsResponseMessage.selectedDisplayText,
    content.listResponseMessage && content.listResponseMessage.title,
    content.templateButtonReplyMessage && content.templateButtonReplyMessage.selectedDisplayText,
  ];

  for (const candidate of candidates) {
    const value = normalizeText(candidate);

    if (value) {
      return value;
    }
  }

  return null;
}

function extractMediaDescriptor(content) {
  if (!content || typeof content !== 'object') {
    return null;
  }

  const descriptors = [
    ['image', content.imageMessage],
    ['document', content.documentMessage],
    ['audio', content.audioMessage],
    ['video', content.videoMessage],
    ['sticker', content.stickerMessage],
  ];

  for (const [type, node] of descriptors) {
    if (node && typeof node === 'object') {
      return { type, node };
    }
  }

  return null;
}

function toNumberValue(value) {
  if (value === null || value === undefined) {
    return null;
  }

  if (typeof value === 'number') {
    return Number.isFinite(value) ? Math.trunc(value) : null;
  }

  if (typeof value === 'bigint') {
    return Number(value);
  }

  if (typeof value === 'string') {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? Math.trunc(parsed) : null;
  }

  if (typeof value === 'object') {
    if (typeof value.low === 'number') {
      return Math.trunc(value.low);
    }

    if (typeof value.toString === 'function') {
      const parsed = Number(value.toString());
      return Number.isFinite(parsed) ? Math.trunc(parsed) : null;
    }
  }

  return null;
}

function resolveMimeType(type, node) {
  const declared = normalizeText(node && node.mimetype);

  if (declared) {
    return declared;
  }

  if (type === 'sticker') {
    return 'image/webp';
  }

  return null;
}

function resolveOriginalName(node) {
  return normalizeText(node && (node.fileName || node.filename || node.displayName));
}

function isAllowedMediaMime(type, mimeType) {
  if (!mimeType) {
    return false;
  }

  if (type === 'sticker') {
    return mimeType === 'image/webp';
  }

  return allowedMediaMimeTypes.has(mimeType);
}

function resolveFileExtension(originalName, mimeType, type) {
  const fromOriginal = path.extname(String(originalName || '')).replace(/^\./, '').trim().toLowerCase();

  if (fromOriginal) {
    return fromOriginal;
  }

  const mapping = {
    'image/jpeg': 'jpg',
    'image/png': 'png',
    'image/webp': 'webp',
    'image/gif': 'gif',
    'application/pdf': 'pdf',
    'application/msword': 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx',
    'application/vnd.ms-excel': 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'xlsx',
    'audio/mpeg': 'mp3',
    'audio/ogg': 'ogg',
    'audio/webm': 'webm',
    'audio/mp4': 'm4a',
    'video/mp4': 'mp4',
    'video/webm': 'webm',
  };

  if (type === 'sticker') {
    return 'webp';
  }

  return mapping[mimeType] || 'bin';
}

function generateSafeMediaFilename(originalName, mimeType, type) {
  const now = new Date();
  const timestamp = [
    now.getFullYear(),
    String(now.getMonth() + 1).padStart(2, '0'),
    String(now.getDate()).padStart(2, '0'),
  ].join('') + '_' + [
    String(now.getHours()).padStart(2, '0'),
    String(now.getMinutes()).padStart(2, '0'),
    String(now.getSeconds()).padStart(2, '0'),
  ].join('');
  const random = crypto.randomBytes(4).toString('hex');
  const extension = resolveFileExtension(originalName, mimeType, type);

  return `wa_${timestamp}_${random}.${extension}`;
}

function buildRejectedMediaPayload(type, mimeType, originalName, size, reason) {
  return {
    has_media: true,
    rejected: true,
    type,
    mime_type: mimeType,
    file_name: null,
    original_name: originalName,
    size,
    url: null,
    path: null,
    reason,
  };
}

function buildPublicMediaUrl(relativePublicPath) {
  return `${PUBLIC_BASE_URL.replace(/\/$/, '')}/storage/${relativePublicPath.replace(/\\/g, '/').replace(/^\/+/, '')}`;
}

function storeInboundMediaBuffer(buffer, type, mimeType, originalName) {
  const directory = mediaDirectories[type] || 'documents';
  const filename = generateSafeMediaFilename(originalName, mimeType, type);
  const relativePublicPath = path.posix.join('whatsapp-media', directory, filename);
  const absolutePath = path.resolve(mediaRootDir, directory, filename);

  ensureMediaDirectoriesExist();
  fs.mkdirSync(path.dirname(absolutePath), { recursive: true });
  fs.writeFileSync(absolutePath, buffer);

  return {
    relativePublicPath,
    storagePath: path.posix.join('storage', 'app', 'public', relativePublicPath),
    url: buildPublicMediaUrl(relativePublicPath),
    fileName: filename,
  };
}

async function extractInboundMediaPayload(baileysMessage, content) {
  const descriptor = extractMediaDescriptor(content);

  if (!descriptor) {
    return { has_media: false };
  }

  const type = descriptor.type;
  const node = descriptor.node;
  const mimeType = resolveMimeType(type, node);
  const originalName = resolveOriginalName(node);
  const declaredSize = toNumberValue(node && node.fileLength);

  if (!isAllowedMediaMime(type, mimeType)) {
    const reason = `Rejected media MIME type: ${mimeType || 'unknown'}`;

    logger.warn({
      message_id: normalizeText(baileysMessage?.key?.id),
      type,
      mime_type: mimeType,
      reason,
    }, 'Inbound media rejected');

    return buildRejectedMediaPayload(type, mimeType, originalName, declaredSize, reason);
  }

  if (declaredSize && declaredSize > maxMediaSizeBytes) {
    const reason = `Rejected media larger than 20MB (${declaredSize} bytes)`;

    logger.warn({
      message_id: normalizeText(baileysMessage?.key?.id),
      type,
      mime_type: mimeType,
      size: declaredSize,
      reason,
    }, 'Inbound media rejected');

    return buildRejectedMediaPayload(type, mimeType, originalName, declaredSize, reason);
  }

  try {
    const buffer = await downloadMediaMessage(
      baileysMessage,
      'buffer',
      {},
      {
        logger,
        reuploadRequest: socket.updateMediaMessage,
      }
    );

    const actualSize = Buffer.isBuffer(buffer) ? buffer.length : null;

    if (!Buffer.isBuffer(buffer) || actualSize === null || actualSize === 0) {
      const reason = 'Failed to download inbound media buffer';
      logger.warn({
        message_id: normalizeText(baileysMessage?.key?.id),
        type,
        mime_type: mimeType,
        reason,
      }, 'Inbound media rejected');

      return buildRejectedMediaPayload(type, mimeType, originalName, declaredSize, reason);
    }

    if (actualSize > maxMediaSizeBytes) {
      const reason = `Rejected media larger than 20MB (${actualSize} bytes)`;
      logger.warn({
        message_id: normalizeText(baileysMessage?.key?.id),
        type,
        mime_type: mimeType,
        size: actualSize,
        reason,
      }, 'Inbound media rejected');

      return buildRejectedMediaPayload(type, mimeType, originalName, actualSize, reason);
    }

    const stored = storeInboundMediaBuffer(buffer, type, mimeType, originalName);

    return {
      has_media: true,
      rejected: false,
      type,
      mime_type: mimeType,
      file_name: stored.fileName,
      original_name: originalName,
      size: actualSize,
      url: stored.url,
      path: stored.storagePath,
    };
  } catch (error) {
    const reason = `Failed to download inbound media: ${error.message}`;

    logger.error({
      message_id: normalizeText(baileysMessage?.key?.id),
      type,
      mime_type: mimeType,
      error: error.message,
    }, 'Inbound media processing failed');

    return buildRejectedMediaPayload(type, mimeType, originalName, declaredSize, reason);
  }
}

function parseReceivedAt(messageTimestamp) {
  const seconds = Number(messageTimestamp);

  if (Number.isFinite(seconds) && seconds > 0) {
    return new Date(seconds * 1000).toISOString();
  }

  return new Date().toISOString();
}

function shouldIgnoreAsOld(messageTimestamp) {
  if (!IGNORE_OLD_MESSAGES) {
    return false;
  }

  const seconds = Number(messageTimestamp);

  if (!Number.isFinite(seconds) || seconds <= 0) {
    return false;
  }

  return seconds < startupUnix;
}

function shouldProcessGroup(groupId, groupName) {
  if (CONFIGURED_GROUP_ID) {
    return normalizeGroupId(groupId) === CONFIGURED_GROUP_ID;
  }

  if (CONFIGURED_GROUP_NAME) {
    return normalizeText(groupName)?.toLowerCase() === CONFIGURED_GROUP_NAME.toLowerCase();
  }

  return true;
}

function bridgeHeaders() {
  const headers = {
    'Content-Type': 'application/json',
  };

  if (BRIDGE_SECRET !== '') {
    headers['X-Bridge-Token'] = BRIDGE_SECRET;
  }

  return headers;
}

async function forwardMessageToLaravel(payload) {
  await httpClient.post(WEBHOOK_URL, payload, {
    headers: bridgeHeaders(),
  });
}

async function sendHeartbeat() {
  if (!socket || !socket.user) {
    return;
  }

  const accountId = normalizePhoneFromJid(socket.user.id);
  const accountName = normalizeText(socket.user.name || socket.user.verifiedName || socket.user.notify);

  const heartbeatPayload = {
    provider: BRIDGE_PROVIDER,
    status: 'connected',
    account_id: accountId,
    account_name: accountName,
    group_id: activeGroupId,
    group_name: activeGroupName,
    meta: {
      groups_count: groupsById.size,
    },
  };

  try {
    await httpClient.post(HEARTBEAT_URL, heartbeatPayload, {
      headers: bridgeHeaders(),
    });
  } catch (error) {
    const responseCode = error.response ? error.response.status : null;

    logger.error({
      error: error.message,
      status: responseCode,
    }, 'Laravel heartbeat request failed');
  }
}

function startHeartbeatLoop() {
  stopHeartbeatLoop();

  heartbeatTimer = setInterval(async () => {
    await sendHeartbeat();
  }, HEARTBEAT_INTERVAL_SECONDS * 1000);
}

function stopHeartbeatLoop() {
  if (heartbeatTimer) {
    clearInterval(heartbeatTimer);
    heartbeatTimer = null;
  }
}

function logGroups(groups) {
  logger.info('Group Name | Group ID');

  for (const [groupId, groupData] of groups.entries()) {
    const subject = normalizeText(groupData.subject) || '(Unnamed Group)';
    logger.info(`${subject} | ${groupId}`);
  }
}

function resolveGroupSelectionFromConfig() {
  if (CONFIGURED_GROUP_ID) {
    const group = groupsById.get(CONFIGURED_GROUP_ID);
    activeGroupId = CONFIGURED_GROUP_ID;
    activeGroupName = normalizeText(group && group.subject) || CONFIGURED_GROUP_NAME;

    logger.info({
      group_id: activeGroupId,
      group_name: activeGroupName,
    }, 'GROUP_ID selected');

    return;
  }

  if (!CONFIGURED_GROUP_NAME) {
    activeGroupId = null;
    activeGroupName = null;
    logger.warn('No GROUP_ID or GROUP_NAME set. The bridge will listen to all groups.');
    return;
  }

  const lookupName = CONFIGURED_GROUP_NAME.toLowerCase();
  const matchedEntry = Array.from(groupsById.entries()).find(([, data]) => normalizeText(data.subject)?.toLowerCase() === lookupName);

  if (!matchedEntry) {
    activeGroupId = null;
    activeGroupName = CONFIGURED_GROUP_NAME;

    logger.warn({
      group_name: CONFIGURED_GROUP_NAME,
    }, 'GROUP_NAME not found in visible groups; listening by name match at runtime');

    return;
  }

  activeGroupId = matchedEntry[0];
  activeGroupName = normalizeText(matchedEntry[1].subject) || CONFIGURED_GROUP_NAME;

  logger.info({
    group_id: activeGroupId,
    group_name: activeGroupName,
  }, 'GROUP_NAME resolved to group ID');
}

async function refreshGroupDirectory() {
  if (!socket) {
    return;
  }

  try {
    const participating = await socket.groupFetchAllParticipating();
    groupsById = new Map(Object.entries(participating));

    logger.info({ groups_count: groupsById.size }, 'Groups discovered');
    logGroups(groupsById);
    resolveGroupSelectionFromConfig();
  } catch (error) {
    logger.error({ error: error.message }, 'Failed to fetch WhatsApp groups');
  }
}

async function processIncomingMessage(baileysMessage) {
  if (!baileysMessage || !baileysMessage.key) {
    return;
  }

  const key = baileysMessage.key;
  const remoteJid = normalizeText(key.remoteJid);

  if (!remoteJid) {
    return;
  }

  const isGroupMessage = remoteJid.endsWith('@g.us');
  const isDirectMessage = remoteJid.endsWith('@s.whatsapp.net');

  if (!isGroupMessage && !isDirectMessage) {
    return;
  }

  if (key.fromMe) {
    return;
  }

  if (shouldIgnoreAsOld(baileysMessage.messageTimestamp)) {
    return;
  }

  let groupId = null;
  let groupName = null;

  if (isGroupMessage) {
    const group = groupsById.get(remoteJid);
    groupName = normalizeText(group && group.subject) || null;

    if (!shouldProcessGroup(remoteJid, groupName)) {
      return;
    }

    groupId = remoteJid;
  }

  const container = resolveMessageContainer(baileysMessage.message);
  const media = await extractInboundMediaPayload(baileysMessage, container);
  const body = extractTextBody(container);

  if (!body && !media.has_media) {
    return;
  }

  const messageType = extractMessageType(container);
  const keySnapshot = toPlainObject(key);

  let fromPhone = null;

  if (isGroupMessage) {
    // For group messages, sender phone comes from participant fields, NOT remoteJid (which is the group JID).
    const participantCandidates = [
      keySnapshot.participantPn,
      keySnapshot.participantPN,
      keySnapshot.participant_pn,
      key.participantPn,
      keySnapshot.participant,
      key.participant,
    ];

    for (const candidate of participantCandidates) {
      const phone = extractPhoneFromJid(candidate);
      if (phone) {
        fromPhone = phone;
        break;
      }
    }

    if (!fromPhone) {
      logger.warn({ remote_jid: remoteJid, msg_id: normalizeText(key.id) }, 'Group message with no valid participant phone — skipped');
      return;
    }
  } else {
    // For direct messages, the sender IS the remoteJid.
    fromPhone = extractPhoneFromJid(remoteJid);

    if (!fromPhone) {
      logger.warn({ remote_jid: remoteJid, msg_id: normalizeText(key.id) }, 'Direct message with no valid phone — skipped');
      return;
    }
  }

  const payload = {
    provider: BRIDGE_PROVIDER,
    provider_message_id: normalizeText(key.id),
    from: fromPhone,
    to: isGroupMessage ? remoteJid : extractPhoneFromJid(socket?.user?.id),
    group_id: groupId,
    group_name: groupName,
    sender_name: normalizeText(baileysMessage.pushName),
    message_type: messageType,
    body,
    media_url: media.has_media && !media.rejected ? media.url : null,
    media,
    raw_payload: baileysMessage,
    received_at: parseReceivedAt(baileysMessage.messageTimestamp),
  };

  try {
    await forwardMessageToLaravel(payload);

    logger.info({
      provider_message_id: payload.provider_message_id,
      from: payload.from,
      group_id: payload.group_id,
      group_name: payload.group_name,
      is_direct: isDirectMessage,
      message_type: messageType,
      has_media: media.has_media || false,
      media_type: media.type || null,
      media_rejected: media.rejected || false,
    }, 'Message forwarded to Laravel');
  } catch (error) {
    const responseCode = error.response ? error.response.status : null;

    logger.error({
      error: error.message,
      status: responseCode,
      provider_message_id: payload.provider_message_id,
    }, 'Failed to forward message to Laravel');
  }
}

function closeReasonCode(lastDisconnect) {
  if (!lastDisconnect || !lastDisconnect.error || typeof lastDisconnect.error.output !== 'object') {
    return null;
  }

  return lastDisconnect.error.output.statusCode || null;
}

// ── Outbound message delivery ──────────────────────────────

async function pollOutboundMessages() {
  if (!socket || !socket.user) {
    return;
  }

  try {
    const response = await httpClient.get(OUTBOUND_PULL_URL, {
      headers: bridgeHeaders(),
      params: { limit: 10 },
    });

    const messages = (response.data && response.data.messages) || [];

    if (messages.length === 0) {
      return;
    }

    logger.info({ count: messages.length }, 'Outbound messages received');

    for (const msg of messages) {
      await sendOutboundMessage(msg);
    }
  } catch (error) {
    const responseCode = error.response ? error.response.status : null;

    logger.error({
      error: error.message,
      status: responseCode,
    }, 'Failed to fetch outbound bridge messages');
  }
}

async function sendOutboundMessage(msg) {
  const messageId = msg.id;
  const text = msg.message || msg.body || '';
  const phone = msg.phone || msg.to_phone || '';
  const groupId = msg.group_id || null;
  const attachment = normalizeAttachmentPayload(msg.attachment);
  const ackUrl = OUTBOUND_PULL_URL + '/' + messageId;

  let targetJid = null;

  if (groupId) {
    targetJid = groupId.includes('@') ? groupId : groupId + '@g.us';
  } else if (phone) {
    const cleanPhone = phone.replace(/^\+/, '').replace(/\D/g, '');
    targetJid = cleanPhone + '@s.whatsapp.net';
  }

  if (!targetJid) {
    await acknowledgeMessage(ackUrl, 'failed', null, 'No valid recipient');
    return;
  }

  try {
    const content = resolveAttachmentContent(attachment, text);

    if (!content) {
      await acknowledgeMessage(ackUrl, 'failed', null, 'Message text or attachment is required');
      return;
    }

    const result = await socket.sendMessage(targetJid, content);
    const sentMsgId = result && result.key ? result.key.id : null;

    logger.info({
      message_id: messageId,
      to: targetJid,
      has_attachment: attachment !== null,
      attachment_type: attachment && attachment.type ? attachment.type : null,
      whatsapp_id: sentMsgId,
    }, 'Outbound message sent via WhatsApp');

    await acknowledgeMessage(ackUrl, 'sent', sentMsgId, null, targetJid);
  } catch (error) {
    logger.error({
      message_id: messageId,
      to: targetJid,
      error: error.message,
    }, 'Failed to send outbound message via WhatsApp');

    await acknowledgeMessage(ackUrl, 'failed', null, error.message);
  }
}

function parseRequestJson(body) {
  const payload = normalizeText(body);

  if (!payload) {
    return {};
  }

  try {
    const decoded = JSON.parse(payload);
    return decoded && typeof decoded === 'object' ? decoded : {};
  } catch (_error) {
    return {};
  }
}

function normalizeAttachmentPayload(value) {
  return value && typeof value === 'object' ? value : null;
}

function resolveMessageTargetJid(to, groupId) {
  const normalizedGroupId = normalizeText(groupId);

  if (normalizedGroupId) {
    return normalizedGroupId.includes('@') ? normalizedGroupId : `${normalizedGroupId}@g.us`;
  }

  const cleanPhone = String(to || '').replace(/^\+/, '').replace(/\D/g, '');

  if (!isValidPhone(cleanPhone)) {
    return null;
  }

  return `${cleanPhone}@s.whatsapp.net`;
}

function resolveAttachmentSource(localPath) {
  if (!localPath) {
    return null;
  }

  if (path.isAbsolute(localPath)) {
    return fs.existsSync(localPath) ? localPath : null;
  }

  // Try the path as-is relative to project root.
  const direct = path.resolve(projectRootDir, localPath);
  if (fs.existsSync(direct)) {
    return direct;
  }

  // Fallback: if the path doesn't start with storage/app/public/, prepend it.
  if (!localPath.startsWith('storage/app/public/') && !localPath.startsWith('storage\\app\\public\\')) {
    const withPrefix = path.resolve(projectRootDir, 'storage', 'app', 'public', localPath);
    if (fs.existsSync(withPrefix)) {
      return withPrefix;
    }
  }

  return null;
}

function resolveAttachmentContent(attachment, messageText) {
  if (!attachment) {
    return normalizeText(messageText) ? { text: messageText } : null;
  }

  const type = normalizeText(attachment.type);
  const mimeType = normalizeText(attachment.mime_type || attachment.mimetype);
  const localPath = normalizeText(attachment.path);
  const remoteUrl = normalizeText(attachment.url);

  let source = null;
  if (localPath) {
    source = resolveAttachmentSource(localPath);
    if (!source) {
      throw new Error(`Attachment file not found: ${localPath}`);
    }
  } else {
    source = remoteUrl;
  }

  if (!source) {
    throw new Error('Attachment path/url is missing.');
  }

  if (!isAllowedMediaMime(type, mimeType)) {
    throw new Error(`Attachment MIME type is not allowed: ${mimeType || 'unknown'}`);
  }

  const caption = normalizeText(messageText) || undefined;

  switch (type) {
    case 'image':
      return {
        image: { url: source },
        mimetype: mimeType || undefined,
        caption,
      };
    case 'document':
      return {
        document: { url: source },
        mimetype: mimeType || undefined,
        fileName: normalizeText(attachment.original_name) || path.basename(String(source)),
        caption,
      };
    case 'audio':
      return {
        audio: { url: source },
        mimetype: mimeType || undefined,
        ptt: false,
      };
    case 'video':
      return {
        video: { url: source },
        mimetype: mimeType || undefined,
        caption,
      };
    case 'sticker':
      return {
        sticker: { url: source },
      };
    default:
      throw new Error(`Unsupported attachment type: ${type || 'unknown'}`);
  }
}

async function sendDirectMessageRequest(payload) {
  if (!socket || !socket.user) {
    throw new Error('Bridge is not connected to WhatsApp.');
  }

  const to = normalizeText(payload.to);
  const groupId = normalizeText(payload.group_id);
  const targetJid = resolveMessageTargetJid(to, groupId);

  if (!targetJid) {
    throw new Error('No valid WhatsApp recipient was provided.');
  }

  const messageText = normalizeText(payload.message) || '';
  const attachment = normalizeAttachmentPayload(payload.attachment);
  const content = resolveAttachmentContent(attachment, messageText);

  if (!content) {
    throw new Error('Message text or attachment is required.');
  }

  logger.info({
    to: targetJid,
    has_attachment: attachment !== null,
    attachment_type: attachment && attachment.type ? attachment.type : null,
    attachment_mime: attachment && attachment.mime_type ? attachment.mime_type : null,
  }, 'Direct WhatsApp send requested');

  const result = await socket.sendMessage(targetJid, content);

  return {
    success: true,
    status: 'sent',
    provider_message_id: result?.key?.id || null,
    sent_to: targetJid,
    response: result || null,
  };
}

async function acknowledgeMessage(ackUrl, status, providerMessageId, errorMsg, sentTo) {
  try {
    await httpClient.post(ackUrl, {
      status,
      provider_message_id: providerMessageId || null,
      error: errorMsg || null,
      sent_to: sentTo || null,
    }, {
      headers: bridgeHeaders(),
    });
  } catch (error) {
    logger.error({
      error: error.message,
      ack_url: ackUrl,
    }, 'Failed to acknowledge outbound message');
  }
}

function startOutboundLoop() {
  stopOutboundLoop();

  outboundTimer = setInterval(async () => {
    await pollOutboundMessages();
  }, OUTBOUND_POLL_SECONDS * 1000);
}

function stopOutboundLoop() {
  if (outboundTimer) {
    clearInterval(outboundTimer);
    outboundTimer = null;
  }
}

// ── Connection ─────────────────────────────────────────────

async function connect() {
  fs.mkdirSync(authDir, { recursive: true });

  const { state, saveCreds } = await useMultiFileAuthState(authDir);
  const { version } = await fetchLatestBaileysVersion();

  socket = makeWASocket({
    auth: state,
    version,
    printQRInTerminal: false,
    logger: pino({ level: 'silent' }),
    syncFullHistory: false,
    markOnlineOnConnect: false,
    browser: ['Task Manager Bridge', 'Chrome', '1.0.0'],
  });

  socket.ev.on('creds.update', saveCreds);

  socket.ev.on('connection.update', async (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (qr) {
      logger.info('QR code generated. Scan it from WhatsApp Linked Devices.');
      qrcode.generate(qr, { small: true });
      connectionState = 'qr_pending';
      try {
        latestQrDataUrl = await QRCode.toDataURL(qr, { width: 512, margin: 2 });
      } catch (_err) {
        latestQrDataUrl = null;
      }
      logger.info('QR shown');
    }

    if (connection === 'open') {
      logger.info('Bridge connected');
      connectionState = 'connected';
      latestQrDataUrl = null;
      await refreshGroupDirectory();
      await sendHeartbeat();
      startHeartbeatLoop();
      startOutboundLoop();
      logger.info({ poll_seconds: OUTBOUND_POLL_SECONDS }, 'Outbound message loop started');
    }

    if (connection === 'close') {
      connectionState = 'disconnected';
      latestQrDataUrl = null;
      stopHeartbeatLoop();
      stopOutboundLoop();

      const reasonCode = closeReasonCode(lastDisconnect);

      // connectionReplaced (440) — another device linked, do NOT reconnect
      const isReplaced = reasonCode === 440
        || reasonCode === DisconnectReason.connectionReplaced;
      const isLoggedOut = reasonCode === DisconnectReason.loggedOut;
      const shouldReconnect = !isReplaced && !isLoggedOut;

      if (isReplaced) {
        logger.error({
          reason_code: reasonCode,
        }, 'WhatsApp session was replaced from another device. Re-link is required. Bridge will NOT auto-reconnect.');
      } else {
        logger.warn({
          reason_code: reasonCode,
          reconnect: shouldReconnect,
        }, 'Bridge disconnected');
      }

      if (shouldReconnect) {
        const delay = Math.min(5000 + Math.random() * 3000, 10000);
        setTimeout(() => {
          connect().catch((error) => {
            logger.error({ error: error.message }, 'Reconnect failed');
          });
        }, delay);
      }
    }
  });

  socket.ev.on('messages.upsert', async ({ messages }) => {
    if (!Array.isArray(messages)) {
      return;
    }

    for (const incomingMessage of messages) {
      try {
        await processIncomingMessage(incomingMessage);
      } catch (error) {
        logger.error({
          error: error.message,
          msg_id: incomingMessage?.key?.id || null,
          remote_jid: incomingMessage?.key?.remoteJid || null,
        }, 'Uncaught error processing incoming message');
      }
    }
  });
}

process.on('unhandledRejection', (reason) => {
  logger.error({ error: String(reason) }, 'Unhandled promise rejection (non-fatal)');
});

process.on('uncaughtException', (error) => {
  logger.fatal({ error: error.message, stack: error.stack }, 'Uncaught exception — process will exit');
  stopHeartbeatLoop();
  stopOutboundLoop();
  process.exit(1);
});

process.on('SIGINT', () => {
  logger.info('Bridge shutting down (SIGINT)');
  stopHeartbeatLoop();
  stopOutboundLoop();
  process.exit(0);
});

process.on('SIGTERM', () => {
  logger.info('Bridge shutting down (SIGTERM)');
  stopHeartbeatLoop();
  stopOutboundLoop();
  process.exit(0);
});

// ── Local HTTP API for QR / logout / status ───────────────

function verifyApiSecret(req) {
  if (BRIDGE_SECRET === '') return true;
  const token = req.headers['x-bridge-token'] || '';
  return token === BRIDGE_SECRET;
}

function jsonResponse(res, statusCode, data) {
  res.writeHead(statusCode, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify(data));
}

function readBody(req) {
  return new Promise((resolve) => {
    const chunks = [];
    req.on('data', (c) => chunks.push(c));
    req.on('end', () => resolve(Buffer.concat(chunks).toString()));
  });
}

const apiServer = http.createServer(async (req, res) => {
  if (!verifyApiSecret(req)) {
    return jsonResponse(res, 401, { error: 'Unauthorized' });
  }

  const url = req.url.split('?')[0];

  // GET /status
  if (req.method === 'GET' && url === '/status') {
    const accountId = socket?.user ? normalizePhoneFromJid(socket.user.id) : null;
    const accountName = socket?.user
      ? normalizeText(socket.user.name || socket.user.verifiedName || socket.user.notify)
      : null;
    return jsonResponse(res, 200, {
      state: connectionState,
      account_id: accountId,
      account_name: accountName,
      qr_available: latestQrDataUrl !== null,
      groups_count: groupsById.size,
    });
  }

  // GET /qr
  if (req.method === 'GET' && url === '/qr') {
    return jsonResponse(res, 200, {
      state: connectionState,
      qr: latestQrDataUrl,
    });
  }

  // POST /logout
  if (req.method === 'POST' && url === '/logout') {
    logger.info('Logout requested via API');
    connectionState = 'disconnected';
    latestQrDataUrl = null;
    stopHeartbeatLoop();
    stopOutboundLoop();

    try {
      if (socket) {
        await socket.logout();
      }
    } catch (err) {
      logger.warn({ error: err.message }, 'Socket logout error (ignored)');
    }

    // Remove auth directory to force new QR
    try {
      fs.rmSync(authDir, { recursive: true, force: true });
      logger.info('Auth directory removed');
    } catch (err) {
      logger.warn({ error: err.message }, 'Failed to remove auth directory');
    }

    // Reconnect (will generate new QR)
    setTimeout(() => {
      connect().catch((error) => {
        logger.error({ error: error.message }, 'Reconnect after logout failed');
      });
    }, 1000);

    return jsonResponse(res, 200, { success: true, message: 'Logged out. New QR code will be generated.' });
  }

  // POST /send-message
  if (req.method === 'POST' && url === '/send-message') {
    const body = await readBody(req);
    const payload = parseRequestJson(body);

    try {
      const result = await sendDirectMessageRequest(payload);
      return jsonResponse(res, 200, result);
    } catch (error) {
      logger.error({
        error: error.message,
        payload_keys: Object.keys(payload || {}),
      }, 'Direct send-message request failed');

      return jsonResponse(res, 422, {
        success: false,
        status: 'failed',
        error: error.message,
      });
    }
  }

  jsonResponse(res, 404, { error: 'Not found' });
});

(async () => {
  logger.info('Starting WhatsApp Web Bridge');
  ensureMediaDirectoriesExist();
  logger.info({
    webhook_url: WEBHOOK_URL,
    heartbeat_url: HEARTBEAT_URL,
    outbound_pull_url: OUTBOUND_PULL_URL,
    ignore_old_messages: IGNORE_OLD_MESSAGES,
    heartbeat_interval_seconds: HEARTBEAT_INTERVAL_SECONDS,
    outbound_poll_seconds: OUTBOUND_POLL_SECONDS,
    api_port: API_PORT,
  }, 'Bridge configuration loaded');

  apiServer.listen(API_PORT, '127.0.0.1', () => {
    logger.info({ port: API_PORT }, 'Bridge API server listening');
  });

  await connect();
})();
