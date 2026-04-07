export const DAY_MS = 24 * 60 * 60 * 1000;

/**
 * @param {object} params
 * @param {number} params.dayStartMs start of calendar day (local midnight)
 * @param {number} params.attackStartMs
 * @param {number} params.attackEndMs
 * @param {number} params.attackId
 * @param {number} params.intensity
 * @returns {{ attack_id: number, offset: number, width: number, intensity: number, roundLeft: boolean, roundRight: boolean } | null}
 */
export function segmentAttackOnDay({ dayStartMs, attackStartMs, attackEndMs, attackId, intensity }) {
  const dayEndMs = dayStartMs + DAY_MS;
  const segmentStartMs = Math.max(dayStartMs, attackStartMs);
  const segmentEndMs = Math.min(dayEndMs, attackEndMs);

  if (segmentStartMs >= segmentEndMs) {
    return null;
  }

  const offset = ((segmentStartMs - dayStartMs) / DAY_MS) * 100;
  const width = ((segmentEndMs - segmentStartMs) / DAY_MS) * 100;
  const roundLeft = segmentStartMs === attackStartMs;
  const roundRight = segmentEndMs === attackEndMs;

  return {
    attack_id: attackId,
    offset,
    width,
    intensity,
    roundLeft,
    roundRight,
  };
}

/**
 * @param {Date} dayDate any instant on the calendar day
 * @param {Array<{ id: number, start_at: string, end_at: string | null, intensity: number }>} attacks
 * @param {Date} [now]
 * @returns {Array<{ attack_id: number, offset: number, width: number, intensity: number, roundLeft: boolean, roundRight: boolean }>}
 */
export function attacksToSegmentsForDay(dayDate, attacks, now = new Date()) {
  const dayStart = new Date(dayDate.getFullYear(), dayDate.getMonth(), dayDate.getDate());
  const dayStartMs = dayStart.getTime();
  const nowMs = now.getTime();
  const segments = [];

  for (const attack of attacks) {
    const attackStartMs = new Date(attack.start_at).getTime();
    const attackEndMs = attack.end_at ? new Date(attack.end_at).getTime() : nowMs;
    const segment = segmentAttackOnDay({
      dayStartMs,
      attackStartMs,
      attackEndMs,
      attackId: attack.id,
      intensity: attack.intensity,
    });

    if (segment) {
      segments.push(segment);
    }
  }

  return segments;
}

export function intensityColorRgba(intensity) {
  if (intensity <= 3) return 'rgba(53, 161, 107, 0.45)';
  if (intensity <= 6) return 'rgba(213, 164, 25, 0.45)';
  if (intensity <= 8) return 'rgba(214, 106, 33, 0.45)';
  return 'rgba(212, 63, 63, 0.45)';
}
