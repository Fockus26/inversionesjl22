// Trailer of the demo copy (docker-compose.demo.yml, invented data): the orders list in
// wp-admin with the store's own statuses, the preview of one order, and back to the list.
// Credentials come from .env.demo.local (scripts/demo-up.sh), passed in the environment.
export const start = "/wp-admin/edit.php?post_type=shop_order";

export async function setup(page) {
  const base = process.env.DEMO_URL ?? "http://localhost:8090";
  await page.goto(`${base}/wp-login.php`);
  await page.fill("#user_login", process.env.DEMO_ADMIN_USER);
  await page.fill("#user_pass", process.env.DEMO_ADMIN_PASSWORD);
  await page.click("#wp-submit");
  await page.waitForURL("**/wp-admin/**");
}

export async function scene(page, h) {
  await h.pause(1800);
  const row = page.locator("#the-list tr").filter({ hasText: "Procesando" }).first();
  await row.hover();
  await h.pause(900);
  await row.locator("a.order-preview").click();
  await page.locator(".wc-backbone-modal-content").waitFor();
  await h.pause(3200);
  await page.locator(".wc-backbone-modal .modal-close").first().click();
  await page.mouse.move(0, 0);
  await h.pause(1800);
}
