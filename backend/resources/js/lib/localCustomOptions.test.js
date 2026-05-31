import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import {
  addLocalCustomOption,
  getLocalCustomOptions,
  isLocalKey,
  loadLocalCustomOptions,
  migrateLocalCustomOptionsToServer,
  toLocalKey,
} from './localCustomOptions';

const USER_ID = 42;

beforeEach(() => {
  const store = new Map();
  globalThis.localStorage = {
    getItem: (key) => store.get(key) ?? null,
    setItem: (key, value) => store.set(key, String(value)),
    removeItem: (key) => store.delete(key),
    clear: () => store.clear(),
  };
});

afterEach(() => {
  localStorage.clear();
});

describe('localCustomOptions', () => {
  it('stores and returns custom options per user', () => {
    const created = addLocalCustomOption(USER_ID, 'triggers', 'Сильный запах');

    expect(isLocalKey(created.key)).toBe(true);
    expect(getLocalCustomOptions(USER_ID, 'triggers')).toEqual([
      { id: created.key.replace('local:', ''), name: 'Сильный запах' },
    ]);
    expect(loadLocalCustomOptions(999)).toEqual({
      triggers: [],
      pain_types: [],
      localizations: [],
      symptoms: [],
      auras: [],
    });
  });

  it('reuses duplicate names in the same category', () => {
    const first = addLocalCustomOption(USER_ID, 'symptoms', 'Озноб');
    const second = addLocalCustomOption(USER_ID, 'symptoms', '  озноб  ');

    expect(second.duplicate).toBe(true);
    expect(second.key).toBe(first.key);
    expect(getLocalCustomOptions(USER_ID, 'symptoms')).toHaveLength(1);
  });

  it('builds local keys', () => {
    expect(toLocalKey('abc')).toBe('local:abc');
  });

  it('migrates local options via callback and clears successful entries', async () => {
    addLocalCustomOption(USER_ID, 'triggers', 'Старый локальный');

    const migrated = await migrateLocalCustomOptionsToServer(USER_ID, async (category, name) => {
      expect(category).toBe('triggers');
      expect(name).toBe('Старый локальный');
    });

    expect(migrated).toBe(1);
    expect(getLocalCustomOptions(USER_ID, 'triggers')).toEqual([]);
  });
});
