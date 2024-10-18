<?php
// src/Controller/OcrController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse; // Agregar esta línea
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

            // Construir el comando con las opciones seleccionadas
            $command = 'ocrmypdf -l spa --force-ocr';
            if ($request->request->get('rotatePages')) {
                $command .= ' --rotate-pages';
            }
            if ($request->request->get('deskew')) {
                $command .= ' --deskew';
            }
            if ($request->request->get('forceOcr')) {
                $command .= ' --force-ocr';
            }
            if ($request->request->get('cleanFinal')) {
                $command .= ' --clean-final';
            }
            $command .= sprintf(' %s %s 2>&1', escapeshellarg($filePath), escapeshellarg($outputFilePath));

            // Log the command for debugging
            $logger->info('Executing command: ' . $command);

            // Inicializar el progreso en la sesión
            $session->set('progress', 0);

            // Establecer el tiempo estimado de procesamiento (en segundos)
            $estimatedTime = 120; // Ajusta este valor según sea necesario

            // Ejecutar el comando
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                $logger->error('Error en el procesamiento OCR: ' . implode("\n", $output));
                return new JsonResponse(['error' => 'Error en el procesamiento OCR'], 500);
            }

            // Actualizar el progreso en la sesión
            $session->set('progress', 100);

            // Devolver el archivo procesado
            return new BinaryFileResponse($outputFilePath);
        } catch (\Exception $e) {
            $logger->error('Error en el procesamiento OCR: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Error en el procesamiento OCR'], 500);
        }
    }
/**
     * @Route("/como-funciona", name="como_funciona", methods={"GET"})
     */
    public function comoFunciona()
    {
        return $this->render('upload/como_funciona.html.twig');
    }
 /**
     * @Route("/contacto", name="contacto", methods={"GET"})
     */
    public function contacto()
    {
        return $this->render('upload/contacto.html.twig');
    }
}





