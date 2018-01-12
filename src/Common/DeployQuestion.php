<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common;

use Symfony\Component\Console\Helper\Table;

class DeployQuestion
{
    /**
     * @var DeployOutput
     */
    protected $output;

    public function __construct(DeployOutput $output)
    {
        assert(valid_num_args());

        $this->output = $output;
    }

    /**
     * Ask environment questions
     */
    public function ask(array $data, array $settings)
    {
        assert(valid_num_args());

        foreach ($settings as $key => $setting) {

            assert(array_key_exists('callable', $setting));
            $callable = $setting['callable'] ?? null;
            assert(is_callable($callable));

            $validate = $setting['validate'] ?? null;
            if (null !== $validate) {
                assert(is_callable($validate));
            }

            $type = $setting['type'] ?? 'string';
            $nullable = isset($setting['nullable']) && true === $setting['nullable'] ? true : false;
            assert(is_bool($nullable));

            $value = $data[$key] ?? null;
            $default = $setting['default'] ?? null;
            $options = $setting['options'] ?? null;

            $done = false;
            while (false === $done) {
                $question = $this->makeQuestion($key, $options, $value, $default);
                $answer = $this->output->ask($question, $value);

                if ($answer === '?') {
                    if (isset($setting['description'])) {
                        $this->output->writeln('');
                        $this->output->writeln('   ' . $setting['description']);
                    }
                } else {
                    $done = $this->processAnswer($key, $answer, $type, $value, $default, $callable, $nullable, $validate);
                }
            }
        }
    }


    /**
     * Ask environment questions
     */
    public function askList(string $title, array $config)
    {
        assert(valid_num_args());

        $load = $config['load'] ?? null;
        assert(is_callable($load));

        $description = (string) $config['description'] ?? null;

        $add = $config['add'] ?? null;
        assert(is_callable($add));

        $remove = $config['remove'] ?? null;
        assert(is_callable($remove));

        $done = false;
        while (false === $done) {

            $data = $load();

            $this->output->header($title);
            if (null !== $description) {
                $this->output->writeln($description);
            }

            $this->listTable($data);
            $this->output->writeln('');
            $option = $this->listMenu();

            if ($option['action'] === 'add') {
                $add($option['value']);
            } elseif ($option['action'] === 'remove') {
                for ($i = 0; $i < count($data); $i++) {
                    if ($option['value'] === $i) {
                        $remove($data[$i]);
                    }
                }
            } elseif ($option['action'] === 'done') {
                $done = true;
            }
        }
    }

    /**
     * Display list table
     */
    protected function listTable($data)
    {
        assert(valid_num_args());

        $table = new Table($this->output->getOutput());
        $rows = [];
        if (count($data) === 0) {
            array_push($rows, ['', 'none']);
        } else {
            for ($i = 0; $i < count($data); $i++) {
                array_push($rows, [$i, $data[$i]]);
            }
        }
        $table->setRows($rows);
        $table->setColumnWidths([5, 69]);
        $table->render();
    }

    /**
     * Display list menu
     */
    protected function listMenu() : array
    {
        assert(valid_num_args());

        $result = [
            'action' => null,
            'value' => null,
        ];

        $this->output->writeln('  Menu Options', 'green');
        $this->output->writeln('  <comment>Options:</comment> add value | remove # | done');
        $this->output->writeln('');

        $answer = $this->output->ask('What would you like to do: ', null, 'yellow');

//        if (null !== $answer) {
            if (null === $answer || $answer === 'done') {
                $result['action'] = 'done';
            } elseif (substr($answer, 0, 3) === 'add') {
                $result['action'] = 'add';
                $result['value'] = trim(substr($answer, 3));
            } elseif (substr($answer, 0, 6) === 'remove') {
                $number = trim(substr($answer, 6));
                $number = filter_var($number, FILTER_VALIDATE_INT);
                if (false !== $number) {
                    $result['action'] = 'remove';
                    $result['value'] = $number;
                }
            }
//        }

        return $result;
    }

    /**
     * Process setting
     */
    protected function makeQuestion(string $key, ?string $options, $value, $default) : string
    {
        assert(valid_num_args());

        $question = [];

        array_push($question, $key);

        if (null !== $options) {
            array_push($question, '(' . $options . ')');
        }

        if (null !== $value) {
            if (is_bool($value)) {
                $value = true === $value ? 'true' : 'false';
            }
            array_push($question, "[$value]");
        } elseif (null !== $default) {
            if (is_bool($default)) {
                $default = true === $default ? 'true' : 'false';
            }
            array_push($question, "[$default]");
        }

        array_push($question, ':');

        return implode(' ', $question);
    }

    /**
     * Process answer
     *
     * @param mixed $answer
     * @param mixed $value
     * @param mixed $default
     */
    protected function processAnswer(string $key, $answer, string $type, $value, $default, callable $callable, bool $nullable = false, ?callable $validate = null) : bool
    {
        assert(valid_num_args());

        if (null === $answer) {
            if (null !== $value) {
                $answer = $value;
            } elseif (null !== $default) {
                $answer = $default;
            }
        }

        if (false === $this->assertNull($key, $answer, $nullable)) {
            return false;
        }

        $valid = $this->validateType($key, $answer, $type, $nullable);
        if (false ===  $valid['success']) {
            $this->output->writeln('   ERROR : ' . $valid['error'], 'red');
            return false;
        }

        if (null !== $validate) {
            if (null !== ($message = $validate($key, $answer))) {
                $this->output->writeln('   VALIDATION ERROR : ' . $message, 'red');
                return false;
            }
        }

        $callable($key, $answer);
        return true;
    }

    /**
     * Assert that the value is allowed to be null
     */
    protected function assertNull(string $key, $answer, bool $nullable = false) : bool
    {
        assert(valid_num_args());

        if (null === $answer) {
            if (false === $nullable) {
                $this->output->writeln("   ERROR : $key can not be NULL");
                return false;
            }
        }

        return true;
    }

    /**
     * Validate that the value is the correct type
     *
     * Return mixed
     */
    protected function validateType(string $key, $answer, string $type, bool $nullable) : array
    {
        assert(valid_num_args());
        assert(in_array($type, ['string', 'int', 'bool']));

        $result = [
            'success' => true,
            'error' => null,
            'value' => $answer
        ];

        if ($type === 'string') {
            $answer = (string) $answer;
            if (!is_string($answer)) {
                $result['success'] = false;
                $result['error'] = 'must be a string';
            }
        } elseif ($type === 'int') {
            $answer = filter_var($answer, FILTER_VALIDATE_INT);
            if (false === $answer) {
                $result['success'] = false;
                $result['error'] = 'must be a integer';
            }
        } elseif ($type === 'bool') {
            if ($answer === 'true' || $answer === '1') {
                $answer = true;
            } elseif ($answer === 'false' || $answer === '0') {
                $answer = false;
            } else {
                $answer = filter_var($answer, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $result;
    }
}
