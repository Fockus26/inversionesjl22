<?php if ( ! defined( 'ABSPATH' ) ) exit; // Evitar acceso directo al archivo ?>

<!DOCTYPE html>
<html>
<head>
  <!-- Meta opcional para tamaño PDF en mm (desactivado) -->
  <!-- <meta name="pdf-size" content="80mm 200mm"> -->

  <!-- Definición del charset para la codificación de caracteres -->
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  
  <!-- Título dinámico de la plantilla, usualmente el tipo de documento -->
  <title><?php echo $this->get_title(); ?></title>

  <!-- Estilos CSS principales de la plantilla -->
  <style type="text/css"><?php $this->template_styles(); ?></style>

  <!-- Estilos personalizados que pueden agregar otros plugins o temas -->
  <style type="text/css"><?php do_action( 'wpo_wcpdf_custom_styles', $this->get_type(), $this ); ?></style>
</head>
<body class="<?php echo $this->get_type(); ?>-template">
  
  <!-- Contenido dinámico del documento PDF (factura, albarán, etc) -->
  <?php echo $content; ?>

</body>
</html>
