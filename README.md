# String Analyzer Service

A RESTful PHP API service that analyzes strings and stores their computed properties.

## Features

For each analyzed string, the service computes and stores:
- **length**: Number of characters
- **is_palindrome**: Boolean indicating if the string is a palindrome (case-insensitive)
- **unique_characters**: Count of distinct characters
- **word_count**: Number of words (separated by whitespace)
- **sha256_hash**: SHA-256 hash for unique identification
- **character_frequency_map**: Object mapping each character to its occurrence count

## Endpoints

### 1. Create/Analyze String
```
POST /strings
```

**Request Body:**
```json
{
  "value": "string to analyze"
}
```

**Success Response (201):**
```json
{
  "id": "sha256_hash_value",
  "value": "string to analyze",
  "properties": {
    "length": 16,
    "is_palindrome": false,
    "unique_characters": 12,
    "word_count": 3,
    "sha256_hash": "abc123...",
    "character_frequency_map": { "s": 2, "t": 3, ... }
  },
  "created_at": "2025-08-27T10:00:00Z"
}
```

### 2. Get Specific String
```
GET /strings/{string_value}
```

**Success Response (200):**
```json
{
  "id": "sha256_hash_value",
  "value": "requested string",
  "properties": { /* same as above */ },
  "created_at": "2025-08-27T10:00:00Z"
}
```

### 3. Get All Strings with Filtering
```
GET /strings?is_palindrome=true&min_length=5&max_length=20&word_count=2&contains_character=a
```

**Query Parameters:**
- `is_palindrome`: boolean (true/false)
- `min_length`: integer (minimum string length)
- `max_length`: integer (maximum string length)
- `word_count`: integer (exact word count)
- `contains_character`: string (single character to search for)

**Success Response (200):**
```json
{
  "data": [ /* array of matching strings */ ],
  "count": 15,
  "filters_applied": { /* applied filters */ }
}
```

### 4. Natural Language Filtering
```
GET /strings/filter-by-natural-language?query=all%20single%20word%20palindromic%20strings
```

**Example Queries:**
- "all single word palindromic strings" → word_count=1, is_palindrome=true
- "strings longer than 10 characters" → min_length=11
- "palindromic strings that contain the letter a" → is_palindrome=true, contains_character=a
- "strings containing the letter z" → contains_character=z

### 5. Delete String
```
DELETE /strings/{string_value}
```

**Success Response (204): No Content**

## Installation & Setup

### Requirements
- PHP 8.0 or higher
- Apache/Nginx web server (for URL rewriting)
- `mod_rewrite` enabled (for Apache)

### Local Development

1. Clone this repository:
   ```bash
   git clone <repository-url>
   cd stage2
   ```

2. Start the PHP development server:
   ```bash
   php -S localhost:8000
   ```

3. The API will be available at `http://localhost:8000`

### Production Deployment

Upload the files to your web hosting platform with PHP support (Railway, Heroku, AWS, etc.).

Ensure:
- `.htaccess` is uploaded for URL rewriting
- PHP version >= 8.0
- Write permissions for the `strings.json` file

## Testing

### Using curl

1. **Add a string:**
   ```bash
   curl -X POST http://localhost:8000/strings \
     -H "Content-Type: application/json" \
     -d '{"value":"hello world"}'
   ```

2. **Get all strings:**
   ```bash
   curl -X GET http://localhost:8000/strings
   ```

3. **Get specific string:**
   ```bash
   curl -X GET "http://localhost:8000/strings/hello%20world"
   ```

4. **Filter strings:**
   ```bash
   curl -X GET "http://localhost:8000/strings?is_palindrome=false&min_length=5"
   ```

5. **Natural language filter:**
   ```bash
   curl -X GET "http://localhost:8000/strings/filter-by-natural-language?query=all%20single%20word%20palindromic%20strings"
   ```

6. **Delete string:**
   ```bash
   curl -X DELETE "http://localhost:8000/strings/hello%20world"
   ```

## Error Responses

- **400 Bad Request**: Invalid request body or missing "value" field
- **422 Unprocessable Entity**: Invalid data type for "value" (must be string) or conflicting filters
- **409 Conflict**: String already exists
- **404 Not Found**: String does not exist or endpoint not found

## Dependencies

- None (uses built-in PHP functions only)

## Environment Variables

- None required
