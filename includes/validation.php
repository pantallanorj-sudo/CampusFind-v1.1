<?php
/**
 * Server-side validation and input cleaning. Client-side checks are only a convenience.
 * Each validate_* function returns ['errors' => [...], 'data' => [...clean values...]].
 */
declare(strict_types=1);

function str_length(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

/** Single-line text: control characters removed, spaces collapsed, trimmed. */
function clean_line($value): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
    return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
}

/** Multi-line text: keeps line breaks, removes other control characters. */
function clean_text($value): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = str_replace("\r\n", "\n", $value);
    $value = preg_replace('/[^\P{C}\n\t]+/u', '', $value) ?? '';
    return trim($value);
}

/** Add an error when a text field is outside the allowed length. */
function check_length(array &$errors, string $field, string $label, string $value, int $min, int $max): void
{
    $len = str_length($value);
    if ($value === '' && $min > 0) {
        $errors[$field] = $label . ' is required.';
    } elseif ($len < $min) {
        $errors[$field] = $label . ' must be at least ' . $min . ' characters.';
    } elseif ($len > $max) {
        $errors[$field] = $label . ' must be at most ' . $max . ' characters.';
    }
}

/** A real calendar date in YYYY-MM-DD, not in the future. */
function check_past_date(array &$errors, string $field, string $label, string $value): void
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if ($value === '') {
        $errors[$field] = $label . ' is required.';
    } elseif (!$date || $date->format('Y-m-d') !== $value) {
        $errors[$field] = $label . ' must be a valid date.';
    } elseif ($date > new DateTimeImmutable('today')) {
        $errors[$field] = $label . ' cannot be in the future.';
    } elseif ((int) $date->format('Y') < 2000) {
        $errors[$field] = $label . ' is too far in the past.';
    }
}

function validate_register_input(array $in): array
{
    $errors = [];
    $name   = clean_line($in['full_name'] ?? '');
    $email  = strtolower(clean_line($in['email'] ?? ''));
    $pass   = is_string($in['password'] ?? null) ? $in['password'] : '';
    $confirm = is_string($in['password_confirm'] ?? null) ? $in['password_confirm'] : '';

    check_length($errors, 'full_name', 'Full name', $name, 2, 100);

    if ($email === '') {
        $errors['email'] = 'Email is required.';
    } elseif (strlen($email) > 150 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if ($pass === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($pass) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (strlen($pass) > 72) {
        $errors['password'] = 'Password must be at most 72 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
        $errors['password'] = 'Password must include at least one letter and one number.';
    }

    if (!isset($errors['password']) && $pass !== $confirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }

    return ['errors' => $errors, 'data' => ['full_name' => $name, 'email' => $email, 'password' => $pass]];
}

/** Fields shared by lost and found reports (create and edit). */
function validate_report_input(array $in): array
{
    $errors = [];
    $data = [
        'item_name'     => clean_line($in['item_name'] ?? ''),
        'description'   => clean_text($in['description'] ?? ''),
        'location'      => clean_line($in['location'] ?? ''),
        'date_occurred' => clean_line($in['date_occurred'] ?? ''),
        'category_id'   => 0,
    ];

    check_length($errors, 'item_name', 'Item name', $data['item_name'], 3, 100);
    check_length($errors, 'description', 'Description', $data['description'], 10, 1000);
    check_length($errors, 'location', 'Location', $data['location'], 3, 150);
    check_past_date($errors, 'date_occurred', 'Date', $data['date_occurred']);

    $categoryId = $in['category_id'] ?? '';
    if (!is_string($categoryId) || !ctype_digit($categoryId) || (int) $categoryId < 1) {
        $errors['category_id'] = 'Choose a category.';
    } else {
        $stmt = db()->prepare('SELECT 1 FROM categories WHERE id = :id');
        $stmt->execute([':id' => (int) $categoryId]);
        if (!$stmt->fetchColumn()) {
            $errors['category_id'] = 'Choose a category from the list.';
        } else {
            $data['category_id'] = (int) $categoryId;
        }
    }
    return ['errors' => $errors, 'data' => $data];
}

function validate_claim_details($value): array
{
    $errors  = [];
    $details = clean_text($value);
    check_length($errors, 'claim_details', 'Claim details', $details, 20, 1500);
    return ['errors' => $errors, 'data' => $details];
}

function validate_staff_notes($value, bool $required): array
{
    $errors = [];
    $notes  = clean_text($value);
    if ($required || $notes !== '') {
        check_length($errors, 'staff_notes', 'Staff notes', $notes, $required ? 5 : 0, 500);
    }
    return ['errors' => $errors, 'data' => $notes];
}

function validate_category_input(array $in, ?int $ignoreId = null): array
{
    $errors = [];
    $name = clean_line($in['name'] ?? '');
    $desc = clean_line($in['description'] ?? '');
    check_length($errors, 'name', 'Category name', $name, 2, 60);
    if ($desc !== '' && str_length($desc) > 200) {
        $errors['description'] = 'Description must be at most 200 characters.';
    }
    if (!isset($errors['name'])) {
        $stmt = db()->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(:name) AND id != :id');
        $stmt->execute([':name' => $name, ':id' => $ignoreId ?? 0]);
        if ($stmt->fetchColumn()) {
            $errors['name'] = 'A category with this name already exists.';
        }
    }
    return ['errors' => $errors, 'data' => ['name' => $name, 'description' => $desc]];
}

// ------------------------------------------------------------- image uploads
/**
 * Check an optional uploaded photo. Returns [error message or null, detected extension or null].
 * The file type is detected from the file CONTENT (getimagesize), never from the file name.
 */
function validate_image_upload(?array $file): array
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];                                      // no photo is fine, it is optional
    }
    if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return ['The photo is too large. The limit is ' . (int) (config('upload_max_bytes') / 1048576) . ' MB.', null];
        }
        return ['The photo could not be uploaded. Please try again.', null];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['The photo upload was not valid.', null];
    }
    if ($file['size'] > (int) config('upload_max_bytes')) {
        return ['The photo is too large. The limit is ' . (int) (config('upload_max_bytes') / 1048576) . ' MB.', null];
    }
    $info = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if ($info === false || !isset($allowed[$info[2]])) {
        return ['The photo must be a JPG, PNG or WebP image.', null];
    }
    if ($info[0] > 8000 || $info[1] > 8000) {
        return ['The photo dimensions are too large (maximum 8000 pixels).', null];
    }
    return [null, $allowed[$info[2]]];
}

/** Save a validated upload under a random name. Returns the path stored in the database. */
function store_uploaded_image(array $file, string $extension): string
{
    $dir = APP_ROOT . '/uploads/items';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('Upload folder is missing.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $extension;       // random, safe filename
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Could not save the uploaded photo.');
    }
    @chmod($dir . '/' . $name, 0644);
    return 'uploads/items/' . $name;
}

function delete_uploaded_image(?string $relativePath): void
{
    if ($relativePath === null || $relativePath === '') {
        return;
    }
    $file = APP_ROOT . '/uploads/items/' . basename($relativePath);
    if (is_file($file)) {
        @unlink($file);
    }
}
