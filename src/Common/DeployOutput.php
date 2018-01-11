<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class DeployOutput
{
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var QuestionHelper
     */
    protected $helper;

    public function __construct(OutputInterface $output, InputInterface $input, QuestionHelper $helper = null)
    {
        assert(valid_num_args());

        $this->output = $output;
        $this->input = $input;
        $this->helper = $helper ?? new QuestionHelper;
    }

    /**
     * Get OutputInterface
     */
    public function getOutput() : OutputInterface
    {
        assert(valid_num_args());

        return $this->output;
    }

    /**
     * Write a line
     */
    public function writeln(string $message, string $color = null)
    {
        assert(valid_num_args());

        $color = $this->getColor($color);
        $this->output->writeln($color['pre'] . '| ' . $message . $color['post']);
    }
    /**
     * Write a message
     */
    public function write(string $message, string $color = null)
    {
        assert(valid_num_args());

        $color = $this->getColor($color);
        $this->output->write($color['pre'] . $message . $color['post']);
    }

    /**
     * Output an error
     */
    public function error(string $message)
    {
        assert(valid_num_args());

        $this->line('red');
        $this->output->writeln('<fg=red>-- ' . $message . '</>');
        $this->line('red');
        exit;
    }

    /**
     * Write line
     */
    public function line($color = null, int $length = 80)
    {
        assert(valid_num_args());

        $color = $this->getColor($color);
        $this->output->writeln($color['pre'] . '|' . str_repeat('-', $length) . $color['post']);
    }

    /**
     * Write blank line
     */
    public function blank()
    {
        assert(valid_num_args());

        $this->output->writeln('');
    }

    /**
     * Write heading
     */
    public function header(string $text, $color = null)
    {
        assert(valid_num_args());

        $color = null !== $color ? $color : 'yellow';

        $this->blank();
        $this->line($color);
        $this->writeln(strtoupper($text), $color);
    }

    /**
     * Write heading
     */
    public function subHeader(string $text, $color = null)
    {
        assert(valid_num_args());

        $color = null !== $color ? $color : 'white';

        $this->writeln('  ' . $text, $color);
        $this->writeln('');
    }

    /**
     * Get color
     */
    protected function getColor(?string $color) : ?array
    {
        assert(valid_num_args());

        $pre = '';
        $post = '';

        if (in_array($color, ['info', 'comment', 'question', 'error'])) {
            $pre = '<' . $color . '>';
            $post = '</' . $color . '>';
        } elseif (null !== $color) {
            $pre = '<fg=' . $color . '>';
            $post = '</>';
        }

        return [
            'pre' => $pre,
            'post' => $post
        ];
    }

    /**
     * Ask
     */
    public function ask(string $question, $default = null, $color = 'info')
    {
        assert(valid_num_args());

        $color = $this->getColor($color);
        $question = new Question($color['pre'] . '|--> ' . $question . $color['post'] . ' ', $default);

        return $this->helper->ask($this->input, $this->output, $question);
    }
}
