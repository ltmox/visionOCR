<?php
// src/Controller/UploadController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class UploadController extends AbstractController
{
    /**
     * @Route("/upload", name="upload")
     */
    public function index(Request $request): Response
    {
        $form = $this->createForm(UploadFileType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();

            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

                try {
                    $file->move($this->getParameter('uploads_directory'), $newFilename);

                    // Ejecutar OCR
                    $process = new Process(['ocrmypdf', $this->getParameter('uploads_directory').'/'.$newFilename, $this->getParameter('uploads_directory').'/processed-'.$newFilename]);
                    $process->run();

                    // Verificar si el proceso falló
                    if (!$process->isSuccessful()) {
                        throw new ProcessFailedException($process);
                    }

                    // Ofrecer el archivo procesado para su descarga
                    return $this->file($this->getParameter('uploads_directory').'/processed-'.$newFilename);

                } catch (FileException $e) {
                    // Manejar el error
                } catch (ProcessFailedException $e) {
                    // Manejar el error
                }
            }
        }

        return $this->render('upload/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}