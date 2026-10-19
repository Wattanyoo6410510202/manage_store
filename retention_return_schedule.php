<?php

function retention_return_date_is_valid(?string $value): bool
{
    if ($value === null || $value === '') {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false) {
        return false;
    }

    if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return false;
    }

    return $date->format('Y-m-d') === $value;
}

