const { createLogger, format, transports } = require('winston');

const logger = createLogger({
  level: process.env.NODE_ENV === 'production' ? 'info' : 'debug',
  format: format.combine(
    format.timestamp({ format: 'DD/MM/YYYY HH:mm:ss' }),
    format.printf(({ timestamp, level, message, ...meta }) => {
      const extra = Object.keys(meta).length ? ' ' + JSON.stringify(meta) : '';
      return `[${timestamp}] ${level.toUpperCase()}: ${message}${extra}`;
    })
  ),
  transports: [new transports.Console()],
});

module.exports = logger;
