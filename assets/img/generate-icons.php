<?php
/**
 * Generador de íconos para PWA
 * 
 * Este script genera automáticamente los íconos necesarios para la PWA
 * a partir de una imagen base.
 */

// Ruta de la imagen base (debe ser cuadrada y de al menos 512x512px)
$baseImage = '24-7ico.png';

// Directorio donde se guardarán los íconos
$outputDir = 'icons/';

// Tamaños de íconos a generar
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

// Crear directorio si no existe
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Verificar que existe la imagen base
if (!file_exists($baseImage)) {
    die("Error: No se encontró la imagen base '$baseImage'. Por favor, coloca una imagen PNG cuadrada en la raíz del proyecto.");
}

// Verificar que GD esté habilitado
if (!extension_loaded('gd')) {
    die("Error: La extensión GD de PHP no está habilitada. Esta extensión es necesaria para procesar imágenes.");
}

// Cargar la imagen base
$sourceImage = imagecreatefrompng($baseImage);
if (!$sourceImage) {
    $sourceImage = imagecreatefromjpeg($baseImage);
}
if (!$sourceImage) {
    die("Error: No se pudo cargar la imagen base. Asegúrate de que es un archivo PNG o JPG válido.");
}

// Obtener dimensiones de la imagen base
$sourceWidth = imagesx($sourceImage);
$sourceHeight = imagesy($sourceImage);

// Verificar que la imagen es cuadrada
if ($sourceWidth !== $sourceHeight) {
    die("Error: La imagen base debe ser cuadrada (misma altura y anchura).");
}

// Verificar que la imagen es lo suficientemente grande
if ($sourceWidth < 512) {
    die("Error: La imagen base debe tener al menos 512x512 píxeles.");
}

echo "Generando íconos...<br>";

// Generar cada tamaño de ícono
foreach ($sizes as $size) {
    // Crear una imagen en blanco con el tamaño requerido
    $destImage = imagecreatetruecolor($size, $size);
    
    // Preservar la transparencia (si la imagen original la tiene)
    imagealphablending($destImage, false);
    imagesavealpha($destImage, true);
    $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
    imagefilledrectangle($destImage, 0, 0, $size, $size, $transparent);
    
    // Redimensionar la imagen
    imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $size, $size, $sourceWidth, $sourceHeight);
    
    // Guardar la imagen redimensionada
    $outputFile = $outputDir . 'icon-' . $size . 'x' . $size . '.png';
    imagepng($destImage, $outputFile);
    
    // Liberar memoria
    imagedestroy($destImage);
    
    echo "Generado ícono de $size x $size píxeles: $outputFile<br>";
}

// Liberar memoria de la imagen base
imagedestroy($sourceImage);

echo "<br>¡Todos los íconos han sido generados correctamente!<br>";
echo "Ahora puedes usar estos íconos en tu manifest.json para la PWA.<br>";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Íconos PWA</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #4a6cf7;
        }
        .instructions {
            margin-top: 30px;
            padding: 15px;
            background: #e9ecef;
            border-left: 4px solid #4a6cf7;
        }
        .note {
            margin-top: 20px;
            padding: 10px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        code {
            background: #f1f1f1;
            padding: 2px 5px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Generador de Íconos para PWA</h1>
        
        <div class="instructions">
            <h3>Instrucciones:</h3>
            <ol>
                <li>Coloca una imagen PNG cuadrada llamada <code>radio-icon.png</code> en la raíz de tu proyecto.</li>
                <li>La imagen debe ser de al menos 512x512 píxeles.</li>
                <li>Ejecuta este script para generar automáticamente todos los tamaños necesarios.</li>
                <li>Los íconos generados se guardarán en la carpeta <code>icons/</code>.</li>
            </ol>
        </div>
        
        <div class="note">
            <strong>Nota:</strong> Este script requiere que la extensión GD de PHP esté habilitada en tu servidor. 
            La mayoría de los servidores web la tienen habilitada por defecto.
        </div>
    </div>
</body>
</html>