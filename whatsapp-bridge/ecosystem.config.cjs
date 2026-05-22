module.exports = {
  apps: [
    {
      name: 'whatsapp-bridge',
      cwd: '/home/u424916160/domains/devline.studio/public_html/task/whatsapp-bridge',
      script: 'src/index.js',
      interpreter: '/opt/alt/alt-nodejs22/root/usr/bin/node',
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
      watch: false,
      restart_delay: 5000,
      exp_backoff_restart_delay: 1000,
      max_memory_restart: '250M',
      time: true,
      env: {
        NODE_ENV: 'production',
      },
    },
  ],
};
