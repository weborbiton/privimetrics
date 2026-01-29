<?php
// ===============================================================================
// PriviMetrics - Dashboard Chart Component
// ===============================================================================
?>

<div class="chart-card">
    <div class="chart-header">
        Visits by
        <?php 
        $periodNames = [
            '24h' => 'Hour',
            '7d'  => 'Day',
            '30d' => 'Day', 
            '90d' => 'Day',
            '1y'  => 'Month',
            '5y'  => 'Year'
        ];
        echo $periodNames[$dateRange] ?? 'Period';
        ?>
    </div>
    <div class="chart">
        <?php
        function getDatesInRange($start, $end) {
            $dates = [];
            $current = $start;
            while ($current <= $end) {
                $dates[] = gmdate('Y-m-d', $current);
                $current = strtotime('+1 day', $current);
            }
            return $dates;
        }

        $range = getDateRange($dateRange);
        $visitsByDate = [];

        if ($dateRange === '24h') {
            for ($h = 0; $h < 24; $h++) {
                $visitsByDate[$h] = 0;
            }
            foreach ($analyticsData as $visit) {
                $hour = (int)$visit['hour'];
                if (isset($visitsByDate[$hour])) $visitsByDate[$hour]++;
            }

        } elseif ($dateRange === '1y') {
            for ($m = 1; $m <= 12; $m++) {
                $visitsByDate[$m] = 0;
            }
            foreach ($analyticsData as $visit) {
                $dateTime = new DateTime($visit['date'], new DateTimeZone('UTC'));
                $month = (int)$dateTime->format('n');
                $visitsByDate[$month]++;
            }

        } elseif ($dateRange === '5y') {
            $currentYear = (int)date('Y');
            $startYear = $currentYear - 4;

            for ($y = $startYear; $y <= $currentYear; $y++) {
                $visitsByDate[$y] = 0;
            }

            foreach ($analyticsData as $visit) {
                $year = (int)(new DateTime($visit['date'], new DateTimeZone('UTC')))->format('Y');
                if (isset($visitsByDate[$year])) $visitsByDate[$year]++;
            }

        } else {
            $allDates = getDatesInRange($range['start'], $range['end']);
            $visitsByDate = array_fill_keys($allDates, 0);
            foreach ($analyticsData as $visit) {
                $dateKey = (new DateTime($visit['date'], new DateTimeZone('UTC')))->format('Y-m-d');
                if (isset($visitsByDate[$dateKey])) $visitsByDate[$dateKey]++;
            }
        }

        $maxVisits = max($visitsByDate);
        $nowUTC = time();
        
        foreach ($visitsByDate as $key => $count) {
            if ($dateRange === '24h') {
                $barTime = strtotime(date('Y-m-d', $nowUTC) . " $key:00 UTC");
            } else {
                $barTime = strtotime($key . ' UTC');
            }

            if ($dateRange === '5y') {
                $color = ($count == 0) ? '#FFD700' : 'var(--accent)';
            } else {
                if ($barTime > $nowUTC) {
                    $color = '#999999'; 
                } elseif ($count == 0) {
                    $color = '#FFD700'; 
                } else {
                    $color = 'var(--accent)';
                }
            }
            
            $heightPercent = $maxVisits > 0 ? ($count / $maxVisits) * 100 : 2;

            if ($dateRange === '24h') {
                $label = $key . ':00';
            } elseif ($dateRange === '1y') {
                $label = 'Month ' . $key;
            } elseif ($dateRange === '5y') {
                $label = (string)$key;
            } else {
                $label = $key;
            }

            echo '<div class="bar" title="'.$label.' - '.$count.' visits" style="height: '.$heightPercent.'%; --bar-color: '.$color.'"></div>';
        }
        ?>
    </div>
</div>
