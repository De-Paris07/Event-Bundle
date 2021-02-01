<?php

namespace ClientEventBundle\Loop;

use Symfony\Component\Console\Style\SymfonyStyle;

class Screen
{
    private $output;
    
    public function __construct(SymfonyStyle $output)
    {
        $this->output = $output;
    }
    
    public function clientConsole(string $text)
    {
        $this->info($text);
    }

    public function comment(string $text): void
    {
        $text = sprintf('<comment>%s</comment>', $text);
        $this->output->writeln((new \DateTime())->format('d-m-Y H:i:s.u') . ' ' . $text);
    }

    public function info(string $text): void
    {
        $text = sprintf('<info>%s</info>', $text);
        $this->output->writeln((new \DateTime())->format('d-m-Y H:i:s.u') . ' ' . $text);
    }

    public function warning(string $text): void
    {
        $this->output->error((new \DateTime())->format('d-m-Y H:i:s.u') . ' ' . $text);
    }
}
