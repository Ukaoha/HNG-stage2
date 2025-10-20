<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

class StringAnalyzer {
    private $dataFile = __DIR__ . '/strings.json';

    public function __construct() {
        if (!file_exists($this->dataFile)) {
            file_put_contents($this->dataFile, json_encode([]));
        }
    }

    private function loadData() {
        $data = json_decode(file_get_contents($this->dataFile), true);
        return $data ?: [];
    }

    private function saveData($data) {
        file_put_contents($this->dataFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function analyzeString($string) {
        $properties = [];

        // Length
        $properties['length'] = strlen($string);

        // Is palindrome (case-insensitive, ignoring spaces)
        $normalized = strtolower(preg_replace('/\s+/', '', $string));
        $properties['is_palindrome'] = ($normalized === strrev($normalized));

        // Unique characters
        $properties['unique_characters'] = count(array_unique(str_split($string)));

        // Word count - using str_word_count for better accuracy
        $properties['word_count'] = str_word_count($string);

        // SHA-256 hash
        $properties['sha256_hash'] = hash('sha256', $string);

        // Character frequency map
        $freq = [];
        foreach (str_split($string) as $char) {
            $freq[$char] = ($freq[$char] ?? 0) + 1;
        }
        $properties['character_frequency_map'] = $freq;

        return $properties;
    }

    private function parseNaturalLanguage($query) {
        $filters = [];
        $query = strtolower(trim($query));

        // Detect palindrome
        if (preg_match('/\bpalindrom(e|ic)\b/', $query)) {
            $filters['is_palindrome'] = true;
        }

        // Detect word count
        if (preg_match('/\bsingle\s+word\b/', $query)) {
            $filters['word_count'] = 1;
        } elseif (preg_match('/\btwo\s+words?\b/', $query)) {
            $filters['word_count'] = 2;
        } elseif (preg_match('/\bthree\s+words?\b/', $query)) {
            $filters['word_count'] = 3;
        } elseif (preg_match('/(\d+)\s+words?/', $query, $match)) {
            $filters['word_count'] = (int)$match[1];
        }

        // Detect length constraints
        if (preg_match('/longer\s+than\s+(\d+)/', $query, $match)) {
            $filters['min_length'] = (int)$match[1] + 1;
        }
        
        if (preg_match('/shorter\s+than\s+(\d+)/', $query, $match)) {
            $filters['max_length'] = (int)$match[1] - 1;
        }

        if (preg_match('/at\s+least\s+(\d+)\s+characters?/', $query, $match)) {
            $filters['min_length'] = (int)$match[1];
        }

        if (preg_match('/at\s+most\s+(\d+)\s+characters?/', $query, $match)) {
            $filters['max_length'] = (int)$match[1];
        }

        if (preg_match('/between\s+(\d+)\s+and\s+(\d+)/', $query, $match)) {
            $filters['min_length'] = (int)$match[1];
            $filters['max_length'] = (int)$match[2];
        }

        // Detect specific character
        if (preg_match('/containing?\s+(the\s+)?letter\s+([a-z])\b/i', $query, $match)) {
            $filters['contains_character'] = strtolower($match[2]);
        } elseif (preg_match('/with\s+(the\s+)?character\s+([a-z])\b/i', $query, $match)) {
            $filters['contains_character'] = strtolower($match[2]);
        } elseif (preg_match('/contains?\s+([a-z])\b/i', $query, $match)) {
            $filters['contains_character'] = strtolower($match[1]);
        }

        // Handle "first vowel" as 'a'
        if (preg_match('/first\s+vowel/', $query)) {
            $filters['contains_character'] = 'a';
        }

        return $filters;
    }

    public function postString() {
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        if (!$input || !isset($input['value'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing "value" field']);
            return;
        }

        $value = $input['value'];

        if (!is_string($value)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid data type for "value" (must be string)']);
            return;
        }

        $data = $this->loadData();
        $hash = hash('sha256', $value);

        if (isset($data[$hash])) {
            http_response_code(409);
            echo json_encode(['error' => 'String already exists in the system']);
            return;
        }

        $properties = $this->analyzeString($value);
        $entry = [
            'id' => $hash,
            'value' => $value,
            'properties' => $properties,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z') // ISO 8601 format in UTC
        ];

        $data[$hash] = $entry;
        $this->saveData($data);

        http_response_code(201);
        echo json_encode($entry);
    }

    public function getString($stringValue) {
        $data = $this->loadData();
        $hash = hash('sha256', $stringValue);

        if (!isset($data[$hash])) {
            http_response_code(404);
            echo json_encode(['error' => 'String does not exist in the system']);
            return;
        }

        http_response_code(200);
        echo json_encode($data[$hash]);
    }

    public function getAllStrings() {
        $data = $this->loadData();
        $filters = [];

        // Apply and validate query parameters
        if (isset($_GET['is_palindrome'])) {
            if (!in_array($_GET['is_palindrome'], ['true', 'false'], true)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid value for is_palindrome (must be true or false)']);
                return;
            }
            $filters['is_palindrome'] = $_GET['is_palindrome'] === 'true';
        }

        if (isset($_GET['min_length'])) {
            if (!ctype_digit($_GET['min_length']) || (int)$_GET['min_length'] < 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid value for min_length (must be non-negative integer)']);
                return;
            }
            $filters['min_length'] = (int)$_GET['min_length'];
        }

        if (isset($_GET['max_length'])) {
            if (!ctype_digit($_GET['max_length']) || (int)$_GET['max_length'] < 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid value for max_length (must be non-negative integer)']);
                return;
            }
            $filters['max_length'] = (int)$_GET['max_length'];
        }

        if (isset($_GET['word_count'])) {
            if (!ctype_digit($_GET['word_count']) || (int)$_GET['word_count'] < 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid value for word_count (must be non-negative integer)']);
                return;
            }
            $filters['word_count'] = (int)$_GET['word_count'];
        }

        if (isset($_GET['contains_character'])) {
            if (strlen($_GET['contains_character']) !== 1) {
                http_response_code(400);
                echo json_encode(['error' => 'contains_character must be a single character']);
                return;
            }
            $filters['contains_character'] = $_GET['contains_character'];
        }

        $filtered = array_filter($data, function($entry) use ($filters) {
            $props = $entry['properties'];
            foreach ($filters as $key => $value) {
                if ($key === 'min_length' && $props['length'] < $value) return false;
                if ($key === 'max_length' && $props['length'] > $value) return false;
                if ($key === 'word_count' && $props[$key] !== $value) return false;
                if ($key === 'is_palindrome' && $props[$key] !== $value) return false;
                if ($key === 'contains_character' && !isset($props['character_frequency_map'][$value])) return false;
            }
            return true;
        });

        $result = [
            'data' => array_values($filtered),
            'count' => count($filtered),
            'filters_applied' => $filters
        ];

        http_response_code(200);
        echo json_encode($result);
    }

    public function filterByNaturalLanguage() {
        if (!isset($_GET['query'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing query parameter']);
            return;
        }

        $query = urldecode($_GET['query']);
        $filters = $this->parseNaturalLanguage($query);

        if (empty($filters)) {
            http_response_code(400);
            echo json_encode(['error' => 'Unable to parse natural language query']);
            return;
        }

        // Check for conflicting filters
        if (isset($filters['min_length']) && isset($filters['max_length']) &&
            $filters['min_length'] > $filters['max_length']) {
            http_response_code(422);
            echo json_encode(['error' => 'Query parsed but resulted in conflicting filters']);
            return;
        }

        $data = $this->loadData();
        $filtered = array_filter($data, function($entry) use ($filters) {
            $props = $entry['properties'];
            foreach ($filters as $key => $value) {
                if ($key === 'min_length' && $props['length'] < $value) return false;
                if ($key === 'max_length' && $props['length'] > $value) return false;
                if ($key === 'word_count' && $props[$key] !== $value) return false;
                if ($key === 'is_palindrome' && $props[$key] !== $value) return false;
                if ($key === 'contains_character' && !isset($props['character_frequency_map'][$value])) return false;
            }
            return true;
        });

        $result = [
            'data' => array_values($filtered),
            'count' => count($filtered),
            'interpreted_query' => [
                'original' => $query,
                'parsed_filters' => $filters
            ]
        ];

        http_response_code(200);
        echo json_encode($result);
    }

    public function deleteString($stringValue) {
        $data = $this->loadData();
        $hash = hash('sha256', $stringValue);

        if (!isset($data[$hash])) {
            http_response_code(404);
            echo json_encode(['error' => 'String does not exist in the system']);
            return;
        }

        unset($data[$hash]);
        $this->saveData($data);

        http_response_code(204);

        
    }

    public function clearAllStrings() {
        $this->saveData([]);
        http_response_code(204);
    }
}

// Routing
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');
$analyzer = new StringAnalyzer();

// Important: Check natural language endpoint BEFORE generic /strings/{value}
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/strings/clear' && $method === 'DELETE') {
    $analyzer->clearAllStrings();
    exit;
}

if ($uri === '/strings/filter-by-natural-language' && $method === 'GET') {
    $analyzer->filterByNaturalLanguage();
    exit;
}

if ($uri === '/strings' && $method === 'POST') {
    $analyzer->postString();
    exit;
}

if ($uri === '/strings' && $method === 'GET') {
    $analyzer->getAllStrings();
    exit;
}

if (preg_match('#^/strings/(.+)$#', $uri, $matches)) {
    $value = urldecode($matches[1]);
    if ($method === 'GET') {
        $analyzer->getString($value);
        exit;
    } elseif ($method === 'DELETE') {
        $analyzer->deleteString($value);
        exit;
    }
}

http_response_code(404);
echo json_encode(['error' => 'Endpoint not found']);
?>