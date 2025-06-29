<?php

class Request {
    /**
     * Retrieves a specific GET parameter with optional filtering and default value.
     *
     * @param string $key The key of the GET parameter.
     * @param mixed $default The default value if the parameter is not set or invalid.
     * @param int $filter The ID of the filter to apply (e.g., FILTER_VALIDATE_INT). Defaults to FILTER_DEFAULT.
     * @param mixed $options Associative array of options to use with filter_input.
     * @return mixed The value of the GET parameter, or the default value.
     */
    public static function get($key, $default = null, $filter = FILTER_DEFAULT, $options = []) {
        // Ensure options is an array if not provided or null
        if (!is_array($options)) {
            $options = [];
        }

        $value = filter_input(INPUT_GET, $key, $filter, $options);

        // filter_input returns NULL if the variable is not set and no 'default' in $options or FILTER_NULL_ON_FAILURE is not used.
        if ($value === null) {
            // If FILTER_NULL_ON_FAILURE was used in $options and validation failed, $value is null.
            // If var simply not set, $value is null.
            // In these cases, our $default should be returned.
            return $default;
        }

        // filter_input returns FALSE on failure for most filters (e.g., validation fails for int, email, etc.).
        // However, for FILTER_VALIDATE_BOOLEAN, FALSE is a valid successfully filtered value.
        if ($value === false && $filter !== FILTER_VALIDATE_BOOLEAN) {
            return $default;
        }
        
        // If $value is not null and not a (non-boolean) false, it's considered valid or the default from filter_input's options.
        return $value;
    }

    /**
     * Retrieves the request body, parsing it if it's JSON.
     *
     * @return mixed The parsed request body (array for JSON/form-data) or an empty array.
     */
    public static function getBody() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (stripos($contentType, 'application/json') !== false) {
                $json_data = file_get_contents('php://input');
                $decoded = json_decode($json_data, true);
                // It's good practice to check for JSON decoding errors, though often omitted for brevity
                // if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) { return $some_error_indicator_or_default; }
                return $decoded;
            } else {
                // Handle form-data or x-www-form-urlencoded
                return $_POST;
            }
        }
        return [];
    }
}
