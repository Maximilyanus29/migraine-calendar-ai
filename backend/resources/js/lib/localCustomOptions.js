export const LOCAL_OPTION_CATEGORIES = [
  'triggers',
  'pain_types',
  'localizations',
  'symptoms',
  'auras',
];

const STORAGE_PREFIX = 'migraine-local-options:v1:';

function storageKey(userId) {
  return `${STORAGE_PREFIX}${userId}`;
}

function emptyStore() {
  return Object.fromEntries(LOCAL_OPTION_CATEGORIES.map((category) => [category, []]));
}

function normalizeName(name) {
  return String(name).trim().replace(/\s+/g, ' ').toLowerCase();
}

export function toLocalKey(id) {
  return `local:${id}`;
}

export function isLocalKey(key) {
  return String(key).startsWith('local:');
}

export function loadLocalCustomOptions(userId) {
  if (!userId) {
    return emptyStore();
  }

  try {
    const raw = localStorage.getItem(storageKey(userId));
    if (!raw) {
      return emptyStore();
    }

    const parsed = JSON.parse(raw);
    const store = emptyStore();

    for (const category of LOCAL_OPTION_CATEGORIES) {
      if (!Array.isArray(parsed[category])) {
        continue;
      }

      store[category] = parsed[category]
        .filter((item) => item?.id && item?.name)
        .map((item) => ({
          id: String(item.id),
          name: String(item.name).trim().replace(/\s+/g, ' '),
        }));
    }

    return store;
  } catch {
    return emptyStore();
  }
}

function saveStore(userId, store) {
  localStorage.setItem(storageKey(userId), JSON.stringify(store));
}

function createId() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }

  return `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 10)}`;
}

export function addLocalCustomOption(userId, category, name) {
  const trimmed = String(name).trim().replace(/\s+/g, ' ');
  const store = loadLocalCustomOptions(userId);
  const list = store[category] ?? [];
  const normalized = normalizeName(trimmed);

  const duplicate = list.find((item) => normalizeName(item.name) === normalized);
  if (duplicate) {
    return {
      key: toLocalKey(duplicate.id),
      name: duplicate.name,
      duplicate: true,
    };
  }

  const entry = { id: createId(), name: trimmed };
  list.push(entry);
  store[category] = list;
  saveStore(userId, store);

  return {
    key: toLocalKey(entry.id),
    name: entry.name,
    duplicate: false,
  };
}

export function getLocalCustomOptions(userId, category) {
  return loadLocalCustomOptions(userId)[category] ?? [];
}

export async function migrateLocalCustomOptionsToServer(userId, createOnServer) {
  if (!userId) {
    return 0;
  }

  const store = loadLocalCustomOptions(userId);
  let migrated = 0;

  for (const category of LOCAL_OPTION_CATEGORIES) {
    const remaining = [];

    for (const item of store[category]) {
      try {
        await createOnServer(category, item.name);
        migrated += 1;
      } catch {
        remaining.push(item);
      }
    }

    store[category] = remaining;
  }

  saveStore(userId, store);

  return migrated;
}

export function getLocalCustomLabels(userId) {
  const store = loadLocalCustomOptions(userId);
  const labels = {};

  for (const category of LOCAL_OPTION_CATEGORIES) {
    labels[category] = {};
    for (const item of store[category]) {
      labels[category][toLocalKey(item.id)] = item.name;
    }
  }

  return labels;
}
