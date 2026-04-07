import { describe, expect, it } from 'vitest';
import {
  DAY_MS,
  attacksToSegmentsForDay,
  intensityColorRgba,
  segmentAttackOnDay,
} from './attackSegments.js';

describe('segmentAttackOnDay', () => {
  it('returns offset and width from local midnight proportion', () => {
    const dayStartMs = Date.parse('2026-04-07T00:00:00');
    const attackStartMs = dayStartMs + 6 * 60 * 60 * 1000;
    const attackEndMs = dayStartMs + 12 * 60 * 60 * 1000;

    const seg = segmentAttackOnDay({
      dayStartMs,
      attackStartMs,
      attackEndMs,
      attackId: 1,
      intensity: 5,
    });

    expect(seg).not.toBeNull();
    expect(seg.offset).toBeCloseTo(25, 5);
    expect(seg.width).toBeCloseTo(25, 5);
    expect(seg.roundLeft).toBe(true);
    expect(seg.roundRight).toBe(true);
  });

  it('splits across midnight: start day only gets left rounding', () => {
    const dayStartMs = Date.parse('2026-04-07T00:00:00');
    const attackStartMs = Date.parse('2026-04-07T22:00:00');
    const attackEndMs = Date.parse('2026-04-08T03:00:00');

    const first = segmentAttackOnDay({
      dayStartMs,
      attackStartMs,
      attackEndMs,
      attackId: 9,
      intensity: 8,
    });

    expect(first).not.toBeNull();
    expect(first.roundLeft).toBe(true);
    expect(first.roundRight).toBe(false);
    expect(first.width).toBeCloseTo((2 / 24) * 100, 5);

    const nextDayStart = dayStartMs + DAY_MS;
    const second = segmentAttackOnDay({
      dayStartMs: nextDayStart,
      attackStartMs,
      attackEndMs,
      attackId: 9,
      intensity: 8,
    });

    expect(second).not.toBeNull();
    expect(second.roundLeft).toBe(false);
    expect(second.roundRight).toBe(true);
    expect(second.offset).toBe(0);
  });

  it('returns null when attack does not intersect the day', () => {
    const dayStartMs = Date.parse('2026-04-07T00:00:00');
    const attackStartMs = Date.parse('2026-04-05T10:00:00');
    const attackEndMs = Date.parse('2026-04-05T11:00:00');

    expect(
      segmentAttackOnDay({
        dayStartMs,
        attackStartMs,
        attackEndMs,
        attackId: 1,
        intensity: 3,
      }),
    ).toBeNull();
  });
});

describe('attacksToSegmentsForDay', () => {
  it('uses now when end_at is null', () => {
    const dayDate = new Date(2026, 3, 7, 12, 0, 0);
    const now = new Date(2026, 3, 7, 15, 0, 0);
    const attacks = [
      {
        id: 3,
        start_at: new Date(2026, 3, 7, 10, 0, 0).toISOString(),
        end_at: null,
        intensity: 4,
      },
    ];

    const segments = attacksToSegmentsForDay(dayDate, attacks, now);
    expect(segments).toHaveLength(1);
    expect(segments[0].roundRight).toBe(true);
    expect(segments[0].width).toBeCloseTo((5 / 24) * 100, 5);
  });
});

describe('intensityColorRgba', () => {
  it('maps buckets', () => {
    expect(intensityColorRgba(1)).toContain('53, 161, 107');
    expect(intensityColorRgba(4)).toContain('213, 164, 25');
    expect(intensityColorRgba(7)).toContain('214, 106, 33');
    expect(intensityColorRgba(10)).toContain('212, 63, 63');
  });
});
