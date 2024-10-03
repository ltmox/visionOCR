<?php
// src/Controller/OcrController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Psr\Log\LoggerInterface;

class OcrController extends AbstractController
{
    /**
     * @Route("/", name="home_page", methods={"GET"})
     */
    public function homePage()
    {
        return $this->render('upload/index.html.twig');
    }

    /**
     * @Route("/upload-pdf", name="upload_pdf", methods={"POST"})
     */
    public function upload(Request $request, SessionInterface $session, LoggerInterface $logger)
    {
        // Aumentar el tiempo máximo de ejecución a 300 segundos (5 minutos)
        set_time_limit(300);

        try {
            $file = $request->files->get('file');
            if (!$file || $file->getMimeType() !== 'application/pdf') {
                $logger->error('Tipo de Archivo Inválido');
                return new JsonResponse(['error' => 'Tipo de Archivo Inválido'], 400);
            }

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/';
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalFilename);
            $extension = strtolower($file->getClientOriginalExtension()); // Obtener la extensión original del archivo en minúsculas
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

            $filePath = $uploadDir . $newFilename;
            $file->move($uploadDir, $newFilename);

            $outputFilePath = $uploadDir . 'OCR_' . $newFilename;
            $command = sprintf(
                'ocrmypdf -l spa --rotate-pages --deskew --force-ocr --clean-final %s %s 2>&1' ,
                escapeshellarg($filePath),
                escapeshellarg($outputFilePath)
            );

            // Log the command for debugging
            $logger->info('Executing command: ' . $command);

            // Inicializar el progreso en la sesión
            $session->set('progress', 0);

            // Establecer el tiempo estimado de procesamiento (en segundos)
            $estimatedTime = 120; // Ajusta este valor según sea necesario
            $startTime = time();

            // Inicializar la variable $output
            $output = '';

            // Ejecutar el comando en segundo plano y actualizar el progreso
            $process = popen($command, 'r');
            if (!$process) {
                throw new \Exception('No se pudo iniciar el proceso OCR');
            }

            $lastProgressUpdate = 0;

            while (!feof($process)) {
                $chunk = fread($process, 4096);
                $output .= $chunk;

                // Calcular el progreso basado en el tiempo transcurrido
                $elapsedTime = time() - $startTime;
                $progress = min(100, ($elapsedTime / $estimatedTime) * 100);

                // Actualizar el progreso en incrementos de 1%
                if ($progress - $lastProgressUpdate >= 1) {
                    $session->set('progress', $progress);
                    $lastProgressUpdate = $progress;
                }

                usleep(500000); // Simular tiempo de procesamiento
            }
            $returnCode = pclose($process);

            if ($returnCode !== 0) {
                $logger->error('Error en el procesamiento OCR: ' . $output);
                throw new \Exception('Falló el procesamiento OCR');
            }

            // Verificar si el archivo de salida existe y es legible
            if (!file_exists($outputFilePath) || !is_readable($outputFilePath)) {
                $logger->error('El archivo de salida no existe o no es legible: ' . $outputFilePath);
                throw new \Exception('El archivo de salida no existe o no es legible: ' . $outputFilePath);
            }

            // Log the successful creation of the file
            $logger->info('Archivo de salida generado correctamente: ' . $outputFilePath);

            // Asegurarse de que el progreso llegue al 100%
            $session->set('progress', 100);

            // Devolver el archivo procesado directamente
            $response = $this->file($outputFilePath);

            // Eliminar los archivos en la carpeta de subida después de la descarga
            $response->deleteFileAfterSend(false); // No eliminar el archivo automáticamente

            // Registrar la eliminación de archivos
            $logger->info('Eliminando archivos en el directorio de subida después de la descarga.');

            // Eliminar los archivos en la carpeta de subida después de la respuesta
            register_shutdown_function(function() use ($uploadDir) {
                $this->deleteFilesInDirectory($uploadDir);
            });

            return $response;
        } catch (\Exception $e) {
            $logger->error('Error en el procesamiento OCR: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Error en el procesamiento OCR', 'detalles' => $e->getMessage()], 500);
        }
    }

    /**
     * @Route("/progress", name="get_progress", methods={"GET"})
     */
    public function getProgress(SessionInterface $session)
    {
        $progress = $session->get('progress', 0);
        return new JsonResponse(['progress' => $progress]);
    }

    /**
     * @Route("/como-funciona", name="como_funciona", methods={"GET"})
     */
    public function comoFunciona()
    {
        return $this->render('upload/como_funciona.html.twig');
    }

    private function deleteFilesInDirectory($directory)
    {
        $files = glob($directory . '/*'); // Obtener todos los archivos en el directorio
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file); // Eliminar el archivo
            }
        }
    }
}