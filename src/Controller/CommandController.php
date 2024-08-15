<?php
// src/Controller/CommandController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\CommandRunner;

class CommandController extends AbstractController
{
    private $commandRunner;

    public function __construct(CommandRunner $commandRunner)
    {
        $this->commandRunner = $commandRunner;
    }

    public function runOcrmypdf(): Response
    {
        $output = $this->commandRunner->runCommand(['ocrmypdf', 'input.pdf', 'output.pdf']);

        return $this->render('command/output.html.twig', [
            'output' => $output,
        ]);
    }
}