<?php 
function formatDateRange($start, $end) {
    $startDate = new DateTime($start);
    $endDate   = new DateTime($end);

    $months = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];

    $startDay = $startDate->format('j');
    $startMonth = (int)$startDate->format('n');
    $startYear = $startDate->format('Y');

    $endDay = $endDate->format('j');
    $endMonth = (int)$endDate->format('n');
    $endYear = $endDate->format('Y');

    // Mismo mes y mismo año → Del 23 al 24 de Enero
    if ($startMonth === $endMonth && $startYear === $endYear) {
        return "Del $startDay al $endDay de {$months[$startMonth]}";
    }

    // Distinto mes, mismo año → Del 23 de Enero al 3 de Febrero
    if ($startYear === $endYear) {
        return "Del $startDay de {$months[$startMonth]} al $endDay de {$months[$endMonth]}";
    }

    // Distinto año → Del 30 de Diciembre del 2026 al 3 de Enero del 2027
    return "Del $startDay de {$months[$startMonth]} del $startYear al $endDay de {$months[$endMonth]} del $endYear";
}
