import type { Project } from "@/content/types";

// English texts not yet reviewed by César.
export const project: Project = {
  slug: "inversionesjl22",
  order: 0, // set in the portfolio
  name: "InversionesJL22",
  kind: { es: "Operación de tienda en línea", en: "Online store operations" },
  sentence: {
    es: "La bodega imprime la factura, escanea el QR y el pedido queda cerrado.",
    en: "The warehouse prints the invoice, scans its QR and the order is closed.",
  },
  summary: {
    es: "Una tienda en línea de Colombia necesitaba que su bodega y su equipo movieran los pedidos sin errores. Ahora la bodega imprime facturas térmicas con QR y cierra cada pedido con el celular, cada persona del equipo ve solo los pedidos de su etapa y el dueño ajusta todo desde un menú propio.",
    en: "A Colombian online store needed its warehouse and staff to move orders without mistakes. Now the warehouse prints thermal invoices with a QR code and closes each order with a phone, each team member sees only the orders of their step, and the owner adjusts it all from one menu.",
  },
  role: {
    es: "Desarrollo de toda la operación interna de la tienda: pedidos, roles, facturas y QR.",
    en: "Built the store's whole back office: orders, roles, invoices and QR.",
  },
  stack: ["WordPress", "WooCommerce", "PHP", "Dompdf", "GitHub Actions"],
  links: [{ type: "live", url: "https://inversionesjl22.com" }],
  media: {
    format: "desktop",
    video: "/work/inversionesjl22/loop",
    poster: "/work/inversionesjl22/poster.jpg",
  },
  client: { name: "InversionesJL22", named: true },
};
