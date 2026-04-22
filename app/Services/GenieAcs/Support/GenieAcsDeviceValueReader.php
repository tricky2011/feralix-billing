<?php

namespace App\Services\GenieAcs\Support;

class GenieAcsDeviceValueReader
{
    /**
     * @param  list<string>  $paths
     */
    public function firstValue(array $device, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = $this->read($device, $path);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    public function read(array $device, string $path): mixed
    {
        if ($path === '') {
            return null;
        }

        $value = data_get($device, $path);

        if ($value === null && array_key_exists($path, $device)) {
            $value = $device[$path];
        }

        return $this->extractValue($value);
    }

    private function extractValue(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_key_exists('_value', $value)) {
                return $this->extractValue($value['_value']);
            }

            if (array_key_exists('value', $value)) {
                return $this->extractValue($value['value']);
            }

            if (array_is_list($value) && count($value) >= 1) {
                return $this->extractValue($value[0]);
            }

            return null;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return null;
    }
}
