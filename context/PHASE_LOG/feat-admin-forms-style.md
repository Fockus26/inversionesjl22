# feat/admin-forms-style (2026-11-11)

Formularios del plugin con el sistema visual del artifact de diseño (tokens en admin-form.css;
borde de campo #8c8494 en vez de #ddd5cd para 3:1). César eligió "todo propio": select y
calendario propios (assets/js/admin-controls.js, patrones APG select-only combobox y date picker
dialog) sobre los campos nativos, que siguen enviándose y reciben "change" (no se tocó el JS
existente). Turno del informe como píldoras (radios nativos, mismo name/valores).

Detalles: errores propios para required (el nativo oculto no puede mostrar el globo; el primer
:invalid de un form es el <fieldset>, por eso FIELDS_INVALID); popups hacia arriba solo si un
ancestro con overflow los corta (modal de la vista previa); fecha corta en el campo ("11 nov
2026"). Verificado con .qa/controles.js (28 comprobaciones, axe sin violaciones, 320 px sin
scroll). Capturas de ficha/vista previa recortadas a las casillas: el resto es PII de clientes.
Hallazgo en código cerrado (no arreglado): la vista previa siempre muestra "Estándar".
