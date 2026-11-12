# fix/qr-aviso-todas-las-pantallas (2026-11-11)

Hallado en staging (sesión real de bodega): el aviso del escaneo era un transient único
(qr_scan_active) que la primera pantalla de bodega que consultaba borraba; con el sitio abierto en
varios equipos solo uno, al azar, lo veía. Ahora: transient inversionesjl22_site_qr_events con los
escaneos de los últimos 120 s (máx. 20) con su hora del servidor; check_qr_scan&since=<now de la
consulta anterior> devuelve los posteriores y el `now` siguiente; nada se borra. since en
sessionStorage (recargar no repite); primera consulta = últimos 20 s; la página a la que redirige
el escaneo usa ?qr_scan_done=<hora> para ver su propio escaneo aunque cargue lento. Sin `since`
(script viejo hasta recargar) responde "nada" para no repetir en bucle.

Verificado en la copia (.qa/qr-varias-pantallas.js, pedido 70372 puesto en Impreso): dos
pantallas + el equipo que escanea ven "La orden paso a completada"; recargar no repite; reescaneo
→ "ya fue completada" en ambas; consulta antigua → nada.
