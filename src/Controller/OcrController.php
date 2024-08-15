<?php
// src/Controller/OcrController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class OcrController extends AbstractController
{
    /**
     * @Route("/upload", name="upload_pdf", methods={"POST"})
     */
    public function upload(Request $request)
    {
        $file = $request->files->get('file');
        if (!$file || $file->getMimeType() !== 'application/pdf') {
            return new JsonResponse(['error' => 'Invalid file type'], 400);
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/';
        $filePath = $uploadDir . $file->getClientOriginalName();
        $file->move($uploadDir, $file->getClientOriginalName());

        $outputFilePath = $uploadDir . 'output_' . $file->getClientOriginalName();
        $command = escapeshellcmd("ocrmypdf -l spa $filePath $outputFilePath");
        $output = shell_exec($command);

        if ($output === null) {
            return new JsonResponse(['error' => 'OCR processing failed'], 500);
        }

        return new JsonResponse(['message' => 'File processed', 'output' => $outputFilePath], 200);
    }

    /**
     * @Route("/upload", name="upload_page", methods={"GET"})
     */
    public function uploadPage()
    {
        return $this->render('upload/index.html.twig');
    }
}