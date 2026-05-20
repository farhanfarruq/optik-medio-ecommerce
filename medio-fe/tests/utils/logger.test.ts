import { describe, it, expect, vi, beforeEach } from 'vitest';
import { logger } from '../../src/core/utils/logger';

describe('logger', () => {
  beforeEach(() => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    vi.spyOn(console, 'warn').mockImplementation(() => {});
    vi.spyOn(console, 'info').mockImplementation(() => {});
    vi.spyOn(console, 'debug').mockImplementation(() => {});
  });

  describe('error', () => {
    it('logs to console.error in dev mode', () => {
      const spy = vi.spyOn(console, 'error');
      logger.error('Test error');
      expect(spy).toHaveBeenCalledWith('Test error');
    });

    it('forwards extra args (Vue error handler signature)', () => {
      const spy = vi.spyOn(console, 'error');
      const err = new Error('boom');
      logger.error('[Vue Error]', err, 'render');
      expect(spy).toHaveBeenCalledWith('[Vue Error]', err, 'render');
    });

    it('handles single ctx arg', () => {
      const spy = vi.spyOn(console, 'error');
      const ctx = { url: '/foo', code: 500 };
      logger.error('Failed', ctx);
      expect(spy).toHaveBeenCalledWith('Failed', ctx);
    });
  });

  describe('warn', () => {
    it('logs to console.warn', () => {
      const spy = vi.spyOn(console, 'warn');
      logger.warn('Suspicious');
      expect(spy).toHaveBeenCalledWith('Suspicious');
    });
  });

  describe('info', () => {
    it('logs to console.info in dev', () => {
      const spy = vi.spyOn(console, 'info');
      logger.info('Operational note');
      expect(spy).toHaveBeenCalledWith('Operational note');
    });
  });

  describe('debug', () => {
    it('logs to console.debug in dev', () => {
      const spy = vi.spyOn(console, 'debug');
      logger.debug('Trace', { x: 1 });
      expect(spy).toHaveBeenCalledWith('Trace', { x: 1 });
    });
  });
});
