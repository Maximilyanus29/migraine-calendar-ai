import { expect, test } from '@playwright/test';

function yesterdayKey() {
  const d = new Date();
  d.setDate(d.getDate() - 1);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function formatDatetimeLocal(date) {
  const offset = date.getTimezoneOffset();
  const local = new Date(date.getTime() - offset * 60 * 1000);
  return local.toISOString().slice(0, 16);
}

function parseDatetimeLocal(value) {
  const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/);
  if (!match) {
    throw new Error(`Bad datetime-local: ${value}`);
  }
  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  const hours = Number(match[4]);
  const minutes = Number(match[5]);
  return new Date(year, month - 1, day, hours, minutes, 0, 0);
}

test('демо-вход и календарь', async ({ page }) => {
  await page.goto('/login');
  await page.getByTestId('login-demo').click();
  await expect(page).toHaveURL(/\/calendar/);
  await expect(page.getByTestId('calendar-grid')).toBeVisible();
});

test('создание приступа и возврат в календарь', async ({ page }) => {
  await page.goto('/login');
  await page.getByTestId('login-demo').click();
  await expect(page).toHaveURL(/\/calendar/);

  const dateKey = yesterdayKey();
  await page.goto(`/attacks/new?date=${dateKey}&ratio=0.25`);

  await expect(page.getByTestId('attack-form-title')).toHaveText('Новый приступ');
  await page.getByTestId('attack-intensity').fill('6');

  const startInput = page.getByLabel('Дата начала');
  const endInput = page.getByLabel('Дата окончания');

  const startDate = parseDatetimeLocal(await startInput.inputValue());
  const endDate = new Date(startDate.getTime() + 45 * 60 * 1000);
  await endInput.fill(formatDatetimeLocal(endDate));

  await page.getByTestId('attack-save').click();
  await expect(page).toHaveURL(/\/calendar/, { timeout: 30_000 });
  await expect(page.getByTestId('calendar-grid')).toBeVisible();
});

test('редактирование приступа и удаление с подтверждением', async ({ page }) => {
  await page.goto('/login');
  await page.getByTestId('login-demo').click();
  await expect(page).toHaveURL(/\/calendar/);

  const dateKey = yesterdayKey();
  await page.goto(`/attacks/new?date=${dateKey}&ratio=0.25`);
  await expect(page.getByTestId('attack-form-title')).toHaveText('Новый приступ');

  await page.getByTestId('attack-intensity').fill('6');

  const startInput = page.getByLabel('Дата начала');
  const endInput = page.getByLabel('Дата окончания');
  const startDate = parseDatetimeLocal(await startInput.inputValue());
  const endDate = new Date(startDate.getTime() + 45 * 60 * 1000);
  await endInput.fill(formatDatetimeLocal(endDate));

  const createRespPromise = page.waitForResponse(
    (r) =>
      r.url().includes('/api/v1/attacks') &&
      r.request().method() === 'POST' &&
      r.status() === 201,
  );
  await page.getByTestId('attack-save').click();
  const createResp = await createRespPromise;
  const body = await createResp.json();
  const attackId = body.data.id;

  await expect(page).toHaveURL(/\/calendar/, { timeout: 30_000 });

  await page.goto(`/attacks/${attackId}/edit`);
  await expect(page.getByTestId('attack-form-title')).toHaveText('Редактирование приступа');
  await page.getByTestId('attack-intensity').fill('8');
  await page.getByTestId('attack-save').click();
  await expect(page).toHaveURL(/\/calendar/, { timeout: 30_000 });

  await page.goto(`/attacks/${attackId}/edit`);
  await expect(page.getByTestId('attack-intensity')).toHaveValue('8');

  page.once('dialog', (dialog) => {
    expect(dialog.type()).toBe('confirm');
    void dialog.accept();
  });

  const deleteRespPromise = page.waitForResponse(
    (r) =>
      r.url().includes(`/api/v1/attacks/${attackId}`) &&
      r.request().method() === 'DELETE' &&
      r.ok(),
  );

  await page.getByTestId('attack-delete').click();
  await deleteRespPromise;
  await expect(page).toHaveURL(/\/calendar/, { timeout: 15_000 });

  await expect(page.locator(`a.segment[href*="/attacks/${attackId}/edit"]`)).toHaveCount(0);
});
