# Pruebas en staging — fase 3 (plugin configurable, versión 1.0.0)

Lista para César antes de mostrárselo al cliente. Nada pasa a producción hasta entonces.
Preparada el 2026-11-14 a partir de los PR #33–#44.

**Antes de empezar**
- Fusionar #44 (adjuntos en correos y Mi cuenta) y esperar el deploy a staging. Cerrar #42 sin fusionar (redundante).
- Tener a mano las tres cuentas de staging: administrador, bodega (`storage_manager`), SEO (`seo_manager`), y una cuenta de cliente con al menos un pedido.
- Staging no envía correos (WP Mail SMTP inactivo + `staging-block-mail.php`): el adjunto del correo no se puede ver llegar allí. Ver el punto 7.

## 0. Arranque (administrador)
- [ ] Plugins: aparece «Inversiones JL22 — Tienda», versión 1.0.0, autor Cesar Leon, activo.
- [ ] «WooCommerce PDF Invoices & Packing Slips» está INACTIVO (migración 007) y no hay aviso de migración fallida.
- [ ] WooCommerce muestra tres entradas nuevas: Estados y flujo, Roles y permisos, Documentos, con pestañas entre ellas.
- [ ] WooCommerce › Estado › Registros: sin errores fatales del plugin desde el deploy.

## 1. Estados y flujo (administrador)
- [ ] Guardar sin cambiar nada: aviso de guardado y la lista de pedidos se ve igual.
- [ ] Cambiar el color y el icono de «Reservado», guardar: la insignia y el botón de acción rápida cambian en Pedidos.
- [ ] Mover un estado con las flechas, guardar: cambia el orden de las pestañas de Pedidos.
- [ ] Crear un estado de prueba, conectarlo (Impreso → prueba → Completado), mover un pedido de prueba por él y devolverlo.
- [ ] Intentar eliminar un estado con pedidos: lo rechaza. Eliminar el de prueba ya vacío: desaparece.
- [ ] «Restaurar valores iniciales» deja los 6 estados de siempre.

## 2. Acciones del administrador en Pedidos
- [ ] En espera ofrece Reservar, Procesar y Cancelar; Impreso ofrece Completar.
- [ ] Vista previa (ojo) de un pedido En espera: botones correctos, ninguno duplicado.
- [ ] Acción en lote «Cambiar estado a…» sobre 2 pedidos de prueba (no se probó nunca con varios pedidos reales).

## 3. Roles y permisos (administrador + cuentas de bodega y SEO)
- [ ] Guardar Bodega y SEO sin cambios; entrar con cada cuenta: misma lista de pedidos, mismas pestañas, mismo menú que antes.
- [ ] Bodega: no ve En espera, Reservado ni Cancelado; no puede abrir un pedido Impreso; no tiene acciones hacia Completado.
- [ ] SEO: ve todos los estados; no puede abrir un Cancelado; puede devolver Procesando → En espera.
- [ ] Quitar a Bodega «Acciones rápidas» Y «Vista previa» hacia Impreso: el botón desaparece de su lista. Restaurar.
- [ ] Dar a Bodega una pantalla más del menú (WooCommerce › Inicio): le aparece y la abre. Restaurar.
- [ ] Bodega y SEO escribiendo la dirección de una pantalla que no es suya (p. ej. Ajustes de WooCommerce, o las tres pantallas nuevas): van al Escritorio.
- [ ] «+ Nuevo rol»: crear «Prueba», marcarle Procesando e Impreso y Pedidos en el menú, asignarlo a un usuario de prueba en Usuarios y entrar con él: ve solo eso. (Nunca se recorrió con un usuario real.)
- [ ] Eliminar el rol con el usuario asignado: lo rechaza. Cambiar el usuario de rol y eliminar: desaparece.

## 4. Escaneo QR
- [ ] Bodega escanea con el teléfono el QR de una factura de un pedido Impreso: pasa a Completado y sale el aviso en sus otras pantallas abiertas. (Nunca se probó con la cámara de un teléfono real.)
- [ ] SEO escanea el mismo tipo de QR: no completa el pedido.
- [ ] En Roles y permisos, dar el QR a SEO y quitárselo a Bodega: se invierte. Restaurar.
- [ ] Escanear sin sesión: lleva al login y, al entrar, vuelve al QR.

## 5. Documentos
- [ ] De fábrica: 80 mm. La factura de un pedido se ve igual que antes de la fase.
- [ ] Cambiar a 58 mm, guardar, generar factura e informe: ancho menor, nada cortado (productos, totales, QR). Fijarse en correos electrónicos largos del cliente.
- [ ] Tipografía y grosor: conservan lo que tenía PDF Invoices (migración 006, sin probar con valores guardados). Cambiarlos y ver el efecto en la factura.
- [ ] «Restaurar valores iniciales» vuelve a 80 mm.
- [ ] Si hay impresora térmica a mano: imprimir una factura a 80 mm (y a 58 mm si existe ese papel).

## 6. Factura e informe de envíos (sin PDF Invoices)
- [ ] Botón de factura en la lista de pedidos, caja «Crear PDF» del pedido y acción en lote «PDF Factura»: los tres abren el PDF.
- [ ] Comparar una factura con una impresa antes de la fase: mismos datos (cliente, cédula, productos, IVA, totales, estado de pago, QR).
- [ ] Bodega: no tiene botón de factura en Completado; SEO: no lo tiene en Impreso, Completado ni Cancelado.
- [ ] Informe de Envíos de un día con pedidos: se genera el PDF y coincide con uno anterior. Con administrador, bodega y SEO.

## 7. Factura para el cliente
- [ ] Mi cuenta › Pedidos con la cuenta de cliente: botón «Factura» en un pedido que ya tenga factura; abre su PDF.
- [ ] El mismo enlace con otra cuenta de cliente o sin sesión: 403, nunca el PDF.
- [ ] Los pedidos creados en staging entre el 2026-11-13 21:25Z y el deploy de #44 no muestran el botón: es esperado.
- [ ] Adjunto en correos: en staging no sale correo. Opciones: (a) darlo por probado en la copia local (5 correos, PDF válido); (b) permitir temporalmente el envío en staging a una sola dirección tuya y hacer un pedido de prueba; decide César.

## 8. Tienda pública (humo)
- [ ] Inicio, una categoría, un producto, carrito, checkout de visitante hasta el pedido: sin errores.
- [ ] El pedido nuevo aparece en wp-admin en el estado correcto y con su estado de pago.

## Pendiente conocido (no es fallo de la prueba)
- El enlace de «Acciones rápidas» y el de «Vista previa» es el mismo: quitar solo una columna oculta el botón pero el servidor sigue aceptando el cambio por la otra.
- Informe de Envíos: sus roles siguen fijos en el código; un rol creado desde la pantalla no lo ve.
- Botones de Mi cuenta del tema: blanco sobre durazno, contraste 1,78:1 (por debajo de AA). Decisión visual pendiente.
- En móvil, las banderas de moneda tapan parte de la columna de acciones de Mi cuenta.
- La descarga de la factura desde wp-admin se llama `invoice-N.pdf`; la del cliente y el adjunto, `factura-N.pdf`.
- La carpeta de PDF Invoices sigue en el repo (solo se desactiva). Borrarla es un paso posterior.

## Al pasar a producción (después de mostrarlo al cliente)
- Corren las migraciones 004–007 en ese deploy; luego purgar Cloudflare.
- Comprobar que Cloudflare no cachea `/wp-admin/admin-ajax.php`.
- Vuelta atrás de los PDF: reactivar PDF Invoices (el plugin propio se aparta solo).
