<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../project_no.php';

if (!project_no_exists($conn, 'PJ-69-675')) {
    fwrite(STDERR, "known project number lookup failed\n");
    exit(1);
}
if (project_no_exists($conn, 'PJ-99-999')) {
    fwrite(STDERR, "unknown project number lookup failed\n");
    exit(1);
}

$generated = generate_unique_project_no($conn, 69);
if (!preg_match('/^PJ-69-\d{3}$/', $generated) || project_no_exists($conn, $generated)) {
    fwrite(STDERR, "unique project number generation failed\n");
    exit(1);
}

$saveSource = file_get_contents(__DIR__ . '/../api/save_project.php');
if (strpos($saveSource, 'project_no_exists') === false || strpos($saveSource, 'mysqli_errno($conn) === 1062') === false) {
    fwrite(STDERR, "save_project duplicate handling failed\n");
    exit(1);
}

echo "project number smoke: PASS\n";
