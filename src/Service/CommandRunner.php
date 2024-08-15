<?php
// src/Service/CommandRunner.php

namespace App\Service;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CommandRunner
{
    public function runCommand(array $command): string
    {
        $process = new Process($command);
        $process->run();

        // Lanza una excepción si el comando falla
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Retorna la salida del comando
        return $process->getOutput();
    }
}