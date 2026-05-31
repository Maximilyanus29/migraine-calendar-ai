export function formatApiError(error, fallback = 'Ошибка запроса') {
  if (!error) {
    return fallback;
  }

  if (!error.payload && error.message) {
    return error.message;
  }

  const payload = error.payload;
  if (payload?.error) {
    return payload.error;
  }
  if (payload?.message) {
    return payload.message;
  }
  if (payload?.errors && typeof payload.errors === 'object') {
    const first = Object.values(payload.errors).flat()[0];
    if (first) {
      return String(first);
    }
  }

  return error.message || fallback;
}

export async function apiRequest(path, options = {}) {
  const { method = 'GET', body } = options;

  const response = await fetch(`/api/v1${path}`, {
    method,
    credentials: 'include',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  const isJson = (response.headers.get('content-type') || '').includes('application/json');
  const payload = isJson ? await response.json() : null;

  if (!isJson) {
    const error = new Error('Unexpected non-JSON response from API');
    error.status = response.status;
    throw error;
  }

  if (!response.ok) {
    const error = new Error(payload?.error || payload?.message || `HTTP ${response.status}`);
    error.status = response.status;
    error.payload = payload;
    throw error;
  }

  return payload?.data;
}
