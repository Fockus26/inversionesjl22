// Trailer of the plugin's own screens in the demo copy (docker-compose.demo.yml, invented
// data, the same plugin as staging and production): the live flow diagram of Estados y flujo
// (hovering a status lights up its arrows), the permissions matrix of Roles y permisos for
// each role, and back to the diagram. Documentos is left out: its PDF preview is an embedded
// PDF, which the headless browser draws blank.
// Credentials come from .env.demo.local (scripts/demo-up.sh), passed in the environment.
export const start = "/wp-admin/admin.php?page=jl22-statuses";

export async function setup(page) {
  const base = process.env.DEMO_URL ?? "http://localhost:8090";
  await page.goto(`${base}/wp-login.php`);
  await page.fill("#user_login", process.env.DEMO_ADMIN_USER);
  await page.fill("#user_pass", process.env.DEMO_ADMIN_PASSWORD);
  await page.click("#wp-submit");
  await page.waitForURL("**/wp-admin/**");
}

const node = (page, name) => page.locator(".jl-node__button").filter({ hasText: name });
const tab = (page, name) => page.locator("a.jl-tab").filter({ hasText: name });

export async function scene(page, h) {
  await h.pause(1000);
  await node(page, "Reservado").hover();
  await h.pause(1100);
  await node(page, "Impreso").hover();
  await h.pause(1100);
  await page.mouse.move(0, 1079);
  await tab(page, "Roles y permisos").click();
  await page.waitForLoadState("networkidle");
  await h.pause(1300);
  await tab(page, "Storage Manager").click();
  await page.waitForLoadState("networkidle");
  await h.pause(1300);
  await tab(page, "Estados y flujo").click();
  await page.waitForLoadState("networkidle");
  await page.mouse.move(0, 1079);
  await h.pause(1000);
}
