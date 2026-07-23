<?php
declare(strict_types=1);

require dirname(__DIR__).'/config.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $schedule = quizScheduleSettings();
    $startDate = $schedule['start_at'] !== ''
        ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $schedule['start_at'])
        : false;
    $endDate = $schedule['end_at'] !== ''
        ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $schedule['end_at'])
        : false;

    if ($startDate && $endDate && $endDate >= $startDate) {
        $start = $startDate->setTime(0, 0);
        $end = $endDate->setTime(0, 0);
        $days = (int)$start->diff($end)->days + 1;
    } else {
        $days = 14;
        $end = new DateTimeImmutable('today');
        $start = $end->modify('-'.($days - 1).' days');
    }

    $statement = db()->prepare(
        'SELECT DATE(submitted_at) traffic_date, COUNT(*) total
         FROM participants
         WHERE submitted_at >= ? AND submitted_at < ?
         GROUP BY DATE(submitted_at)
         ORDER BY traffic_date'
    );
    $statement->execute([
        $start->format('Y-m-d 00:00:00'),
        $end->modify('+1 day')->format('Y-m-d 00:00:00'),
    ]);

    $byDate = [];
    foreach ($statement->fetchAll() as $row) {
        $byDate[(string)$row['traffic_date']] = (int)$row['total'];
    }

    $labels = [];
    $values = [];
    for ($index = 0; $index < $days; $index++) {
        $day = $start->modify('+'.$index.' days');
        $dateKey = $day->format('Y-m-d');
        $labels[] = $day->format('d/m');
        $values[] = $byDate[$dateKey] ?? 0;
    }

    $quota = dailyParticipantQuota();
    $today = $byDate[(new DateTimeImmutable('today'))->format('Y-m-d')] ?? 0;
    $usedPercent = min(100, round(($today / $quota) * 100, 1));
    $hoursElapsed = max(1 / 60, (time() - (new DateTimeImmutable('today'))->getTimestamp()) / 3600);
    $perHour = round($today / $hoursElapsed, 1);
    $etaHours = $perHour > 0 ? max(0, ($quota - $today) / $perHour) : null;

    if ($today >= $quota) {
        $etaLabel = 'Sudah habis';
    } elseif ($etaHours === null) {
        $etaLabel = 'Belum dapat dihitung';
    } elseif ($etaHours >= 24) {
        $etaLabel = 'Lebih dari 24 jam';
    } else {
        $etaLabel = number_format($etaHours, 1, ',', '.').' jam lagi';
    }

    echo json_encode([
        'ok' => true,
        'labels' => $labels,
        'values' => $values,
        'quota' => $quota,
        'days' => $days,
        'today' => $today,
        'used_percent' => $usedPercent,
        'per_hour' => $perHour,
        'eta_label' => $etaLabel,
        'updated_at' => date('H:i:s'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Data trafik belum dapat dimuat.']);
}
