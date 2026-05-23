module.exports = {
  apps: [
    {
      name: 'whatsapp-bridge',
      cwd: '/home/u424916160/domains/devline.studio/public_html/task/whatsapp-bridge',
      script: './src/index.js',
      interpreter: '/opt/alt/alt-nodejs20/root/usr/bin/node',
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
      watch: false,
      max_restarts: 50,
      min_uptime: '10s',
      restart_delay: 5000,
      exp_backoff_restart_delay: 1000,
      max_memory_restart: '300M',
      time: true,
      kill_timeout: 5000,
      listen_timeout: 10000,
      ignore_watch: ['auth', 'node_modules', '*.log', 'logs'],
      env: {
        NODE_ENV: 'production',
      },
    },
  ],
};
