<?php

if (!function_exists('project_no_exists')) {
    function project_no_exists(mysqli $conn, string $projectNo): bool
    {
        $stmt = $conn->prepare('SELECT id FROM projects WHERE project_no = ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('ไม่สามารถตรวจสอบเลขที่โครงการได้');
        }
        $stmt->bind_param('s', $projectNo);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('ไม่สามารถตรวจสอบเลขที่โครงการได้');
        }
        $result = $stmt->get_result();
        $exists = $result && $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }
}

if (!function_exists('generate_unique_project_no')) {
    function generate_unique_project_no(mysqli $conn, ?int $year = null): string
    {
        $year = $year ?? ((int)date('Y') + 543) % 100;
        $year = $year % 100;
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $candidate = sprintf('PJ-%02d-%03d', $year, random_int(1, 999));
            if (!project_no_exists($conn, $candidate)) {
                return $candidate;
            }
        }

        for ($suffix = 1; $suffix <= 999; $suffix++) {
            $candidate = sprintf('PJ-%02d-%03d', $year, $suffix);
            if (!project_no_exists($conn, $candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('เลขที่โครงการอัตโนมัติของปีนี้เต็มแล้ว กรุณาระบุเลขที่โครงการเอง');
    }
}
