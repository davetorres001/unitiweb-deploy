<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common;

trait ConfigTrait
{
    /**
     * Assert that the value is the correct type
     */
    protected function assertType(string $key, $value, array $config)
    {
        assert(valid_num_args());
        assert(array_key_exists($key, $config));

        $type = $config[$key];

        if ($type === 'string') {
            assert(is_string($value));
        } elseif ($type === 'int') {
            $value = filter_var($value, FILTER_VALIDATE_INT);
            assert(is_int($value));
        } elseif ($type === 'bool') {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            assert(is_bool($value));
        }
    }

    /**
     * Prepare an array for saving
     */
    protected function prepareArraytoSave(array $array) : array
    {
        assert(valid_num_args());

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (count($value) === 0) {
                    $array[$key] = null;
                } else {
                    $array[$key] = $this->prepareArraytoSave($array[$key]);
                }
            } else {
                $array[$key] = $value;
            }
        }

        return $array;
    }
}
