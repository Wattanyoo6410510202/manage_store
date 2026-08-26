<?php
require_once __DIR__ . '/../inspection_workflow.php';

$cases = [
    ['status' => 'pass', 'photos' => 0, 'expects_error' => true],
    ['status' => 'fail', 'photos' => 0, 'expects_error' => true],
    ['status' => 'conditional_pass', 'photos' => 0, 'expects_error' => true],
    ['status' => 'not_applicable', 'photos' => 0, 'expects_error' => false],
    ['status' => 'pass', 'photos' => 1, 'expects_error' => false],
    ['status' => '', 'photos' => 0, 'expects_error' => false],
];

foreach ($cases as $case) {
    $error = inspection_validate_result_photo($case['status'], $case['photos']);
    $hasError = $error !== null;
    if ($hasError !== $case['expects_error']) {
        fwrite(STDERR, sprintf(
            "Unexpected photo validation for status %s with %d photos\n",
            $case['status'] === '' ? '(empty)' : $case['status'],
            $case['photos']
        ));
        exit(1);
    }
}

$stepError = inspection_validate_step_photos(
    [
        ['id' => 11, 'result_status' => 'pass'],
        ['id' => 12, 'result_status' => 'not_applicable'],
    ],
    [12 => 0]
);
if ($stepError === null) {
    fwrite(STDERR, "Inspector approval accepted a checked item without a photo\n");
    exit(1);
}

$completeStepError = inspection_validate_step_photos(
    [
        ['id' => 11, 'result_status' => 'pass'],
        ['id' => 12, 'result_status' => 'not_applicable'],
    ],
    [11 => 1]
);
if ($completeStepError !== null) {
    fwrite(STDERR, "Inspector approval rejected a complete photo set\n");
    exit(1);
}

echo "inspection photo requirement smoke: PASS\n";
