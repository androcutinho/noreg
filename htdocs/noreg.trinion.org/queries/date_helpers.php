<?php

// Month mapping for simple format (lowercase)
$months_ru_simple = [
    'January' => 'января',
    'February' => 'февраля',
    'March' => 'марта',
    'April' => 'апреля',
    'May' => 'мая',
    'June' => 'июня',
    'July' => 'июля',
    'August' => 'августа',
    'September' => 'сентября',
    'October' => 'октября',
    'November' => 'ноября',
    'December' => 'декабря'
];

// Month mapping for table display (capitalized)
$months_ru_capitalized = [
    'January' => 'Январь',
    'February' => 'Февраль',
    'March' => 'Март',
    'April' => 'Апрель',
    'May' => 'Май',
    'June' => 'Июнь',
    'July' => 'Июль',
    'August' => 'Август',
    'September' => 'Сентябрь',
    'October' => 'Октябрь',
    'November' => 'Ноябрь',
    'December' => 'Декабрь'
];

// Function to format date in Russian (simple format)
function formatDateRussian($date_str, $months_array) {
    $date = strtotime($date_str);
    $day = date('j', $date);
    $month = $months_array[date('F', $date)];
    $year = date('Y', $date);
    return $day . ' ' . $month . ' ' . $year . ' года';
}

// Function to format date in formal Russian text (ordinal, genitive case)
function formatDateFormalRussian($date_str) {
    $date = strtotime($date_str);
    $day = date('j', $date);
    $month = date('n', $date);
    $year = date('Y', $date);
    
    // Day names in Russian (ordinal, genitive case)
    $days_ru = [
        1 => 'Первого', 2 => 'Второго', 3 => 'Третьего', 4 => 'Четвёртого', 5 => 'Пятого',
        6 => 'Шестого', 7 => 'Седьмого', 8 => 'Восьмого', 9 => 'Девятого', 10 => 'Десятого',
        11 => 'Одиннадцатого', 12 => 'Двенадцатого', 13 => 'Тринадцатого', 14 => 'Четырнадцатого', 15 => 'Пятнадцатого',
        16 => 'Шестнадцатого', 17 => 'Семнадцатого', 18 => 'Восемнадцатого', 19 => 'Девятнадцатого', 20 => 'Двадцатого',
        21 => 'Двадцать первого', 22 => 'Двадцать второго', 23 => 'Двадцать третьего', 24 => 'Двадцать четвёртого', 25 => 'Двадцать пятого',
        26 => 'Двадцать шестого', 27 => 'Двадцать седьмого', 28 => 'Двадцать восьмого', 29 => 'Двадцать девятого', 30 => 'Тридцатого', 31 => 'Тридцать первого'
    ];
    
    // Month names in Russian (genitive case)
    $months_ru = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня',
        7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
    ];
    
    // Year in Russian (genitive case for ordinal)
    $year_str = '';
    if ($year >= 1000) {
        $thousands = intval($year / 1000);
        $remainder = $year % 1000;
        
        $thousands_words = ['', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'];
        $year_str .= ucfirst($thousands_words[$thousands]) . ' тысяч' . ($thousands == 1 ? 'а' : 'и');
        
        if ($remainder > 0) {
            $hundreds = intval($remainder / 100);
            $remainder = $remainder % 100;
            $hundreds_words = ['', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот', 'шестьсот', 'семьсот', 'восемьсот', 'девятьсот'];
            $year_str .= ' ' . $hundreds_words[$hundreds];
            
            if ($remainder > 0) {
                if ($remainder >= 20) {
                    $tens = intval($remainder / 10);
                    $ones = $remainder % 10;
                    $tens_words = ['', '', 'двадцать', 'тридцать', 'сорок', 'пятьдесят', 'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто'];
                    $ones_words = ['', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'];
                    $year_str .= ' ' . $tens_words[$tens];
                    if ($ones > 0) {
                        $year_str .= ' ' . $ones_words[$ones];
                    }
                } else {
                    $teens_words = ['десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать', 'пятнадцать', 'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать'];
                    $year_str .= ' ' . $teens_words[$remainder - 10];
                }
            }
        }
        $year_str .= ' ' . (substr($year, -2) == '00' || substr($year, -2) == '01' ? 'года' : 'года');
    }
    
    return $days_ru[$day] . ' ' . $months_ru[$month] . ' ' . trim($year_str);
}
?>
