require('dotenv').config();

const fs = require('fs');
const http = require('http');
const path = require('path');
const axios = require('axios');
const pino = require('pino');
const qrcode = require('qrcode-terminal');
const QRCode = require('qrcode');
const {
  default: makeWASocket,
  useMultiFileAuthState,
  fetchLatestBaileysVersion,
  DisconnectReason,
} = require('@whiskeysockets/baileys');

const logger = pino({
  level: process.env.LOG_LEVEL || 'info',
});

const WEBHOOK_URL = process.env.LARAVEL_WEBHOOK_URL || 'http://127.0.0.1:8000/webhooks/inbound-message';
const HEARTBEAT_URL = process.env.LARAVEL_HEARTBEAT_URL || 'http://127.0.0.1:8000/webhooks/bridge/heartbeat';
const OUTBOUND_PULL_URL = process.env.LARAVEL_OUTBOUND_PULL_URL || deriveOutboundUrl();
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

function deriveOutboundUrl() {
  const base = (process.env.LARAVEL_HEARTBEAT_URL || 'http://127.0.0.1:8000/webhooks/bridge/heartbeat')
    .replace(/\/heartbeat\/?$/, '');
  return base + '/outbound';
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

function normalizePhoneFromJid(jid) {
  const raw = normalizeText(jid);

  if (!raw) {
    return null;
  }

  const withoutDomain = raw.split('@')[0] || raw;
  const withoutDevice = withoutDomain.split(':')[0] || withoutDomain;
  const normalized = withoutDevice.trim();

  return normalized === '' ? null : normalized;
}

function normalizeIncomingPhone(value) {
  const text = normalizeText(value);

  if (!text) {
    return null;
  }

  return text.replace(/^\+/, '');
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

  return type;
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

  if (!remoteJid || !remoteJid.endsWith('@g.us')) {
    return;
  }

  if (key.fromMe) {
    return;
  }

  if (shouldIgnoreAsOld(baileysMessage.messageTimestamp)) {
    return;
  }

  const group = groupsById.get(remoteJid);
  const groupName = normalizeText(group && group.subject) || null;

  if (!shouldProcessGroup(remoteJid, groupName)) {
    return;
  }

  const container = resolveMessageContainer(baileysMessage.message);
  const body = extractTextBody(container);

  if (!body) {
    return;
  }

  const messageType = extractMessageType(container);
  const keySnapshot = toPlainObject(key);

  // Prefer participant phone jid when available because participant can be a WhatsApp LID (internal id).
  const participantJid = keySnapshot.participantPn
    || keySnapshot.participantPN
    || keySnapshot.participant_pn
    || key.participantPn
    || keySnapshot.participant
    || key.participant
    || key.remoteJid;
  const fromParticipant = normalizeIncomingPhone(normalizePhoneFromJid(participantJid));

  if (!fromParticipant) {
    return;
  }

  const payload = {
    provider: BRIDGE_PROVIDER,
    provider_message_id: normalizeText(key.id),
    from: fromParticipant,
    to: remoteJid,
    group_id: remoteJid,
    group_name: groupName,
    sender_name: normalizeText(baileysMessage.pushName),
    message_type: messageType,
    body,
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
  const ackUrl = OUTBOUND_PULL_URL + '/' + messageId;

  if (!text) {
    await acknowledgeMessage(ackUrl, 'failed', null, 'Empty message body');
    return;
  }

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
    const result = await socket.sendMessage(targetJid, { text });
    const sentMsgId = result && result.key ? result.key.id : null;

    logger.info({
      message_id: messageId,
      to: targetJid,
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
      const shouldReconnect = reasonCode !== DisconnectReason.loggedOut;

      logger.warn({
        reason_code: reasonCode,
        reconnect: shouldReconnect,
      }, 'Bridge disconnected');

      if (shouldReconnect) {
        setTimeout(() => {
          connect().catch((error) => {
            logger.error({ error: error.message }, 'Reconnect failed');
          });
        }, 2000);
      }
    }
  });

  socket.ev.on('messages.upsert', async ({ messages }) => {
    if (!Array.isArray(messages)) {
      return;
    }

    for (const incomingMessage of messages) {
      await processIncomingMessage(incomingMessage);
    }
  });
}

process.on('SIGINT', () => {
  logger.info('Bridge shutting down');
  stopHeartbeatLoop();
  stopOutboundLoop();
  process.exit(0);
});

process.on('SIGTERM', () => {
  logger.info('Bridge shutting down');
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

  jsonResponse(res, 404, { error: 'Not found' });
});

(async () => {
  logger.info('Starting WhatsApp Web Bridge');
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
