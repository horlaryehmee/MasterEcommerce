<?php

/*
| States / regions offered as a dropdown at checkout and in shipping zones,
| using the same codes as WooCommerce (e.g. "NG:LA" = Lagos, Nigeria).
| Countries not listed here get a free-text "State / region" field.
| Add more countries as needed: 'XX' => ['CODE' => 'Name', ...].
*/

return [
    'NG' => [
        'AB' => 'Abia', 'FC' => 'Abuja (FCT)', 'AD' => 'Adamawa', 'AK' => 'Akwa Ibom', 'AN' => 'Anambra',
        'BA' => 'Bauchi', 'BY' => 'Bayelsa', 'BE' => 'Benue', 'BO' => 'Borno', 'CR' => 'Cross River',
        'DE' => 'Delta', 'EB' => 'Ebonyi', 'ED' => 'Edo', 'EK' => 'Ekiti', 'EN' => 'Enugu',
        'GO' => 'Gombe', 'IM' => 'Imo', 'JI' => 'Jigawa', 'KD' => 'Kaduna', 'KN' => 'Kano',
        'KT' => 'Katsina', 'KE' => 'Kebbi', 'KO' => 'Kogi', 'KW' => 'Kwara', 'LA' => 'Lagos',
        'NA' => 'Nasarawa', 'NI' => 'Niger', 'OG' => 'Ogun', 'ON' => 'Ondo', 'OS' => 'Osun',
        'OY' => 'Oyo', 'PL' => 'Plateau', 'RI' => 'Rivers', 'SO' => 'Sokoto', 'TA' => 'Taraba',
        'YO' => 'Yobe', 'ZA' => 'Zamfara',
    ],
    'GH' => [
        'AF' => 'Ahafo', 'AH' => 'Ashanti', 'BA' => 'Bono', 'BE' => 'Bono East', 'CP' => 'Central',
        'EP' => 'Eastern', 'AA' => 'Greater Accra', 'NE' => 'North East', 'NP' => 'Northern', 'OT' => 'Oti',
        'SV' => 'Savannah', 'UE' => 'Upper East', 'UW' => 'Upper West', 'TV' => 'Volta', 'WP' => 'Western',
        'WN' => 'Western North',
    ],
    'KE' => [
        'KE01' => 'Baringo', 'KE02' => 'Bomet', 'KE03' => 'Bungoma', 'KE04' => 'Busia', 'KE05' => 'Elgeyo-Marakwet',
        'KE06' => 'Embu', 'KE07' => 'Garissa', 'KE08' => 'Homa Bay', 'KE09' => 'Isiolo', 'KE10' => 'Kajiado',
        'KE11' => 'Kakamega', 'KE12' => 'Kericho', 'KE13' => 'Kiambu', 'KE14' => 'Kilifi', 'KE15' => 'Kirinyaga',
        'KE16' => 'Kisii', 'KE17' => 'Kisumu', 'KE18' => 'Kitui', 'KE19' => 'Kwale', 'KE20' => 'Laikipia',
        'KE21' => 'Lamu', 'KE22' => 'Machakos', 'KE23' => 'Makueni', 'KE24' => 'Mandera', 'KE25' => 'Marsabit',
        'KE26' => 'Meru', 'KE27' => 'Migori', 'KE28' => 'Mombasa', 'KE29' => 'Murang’a', 'KE30' => 'Nairobi County',
        'KE31' => 'Nakuru', 'KE32' => 'Nandi', 'KE33' => 'Narok', 'KE34' => 'Nyamira', 'KE35' => 'Nyandarua',
        'KE36' => 'Nyeri', 'KE37' => 'Samburu', 'KE38' => 'Siaya', 'KE39' => 'Taita-Taveta', 'KE40' => 'Tana River',
        'KE41' => 'Tharaka-Nithi', 'KE42' => 'Trans Nzoia', 'KE43' => 'Turkana', 'KE44' => 'Uasin Gishu', 'KE45' => 'Vihiga',
        'KE46' => 'Wajir', 'KE47' => 'West Pokot',
    ],
    'ZA' => [
        'EC' => 'Eastern Cape', 'FS' => 'Free State', 'GP' => 'Gauteng', 'KZN' => 'KwaZulu-Natal', 'LP' => 'Limpopo',
        'MP' => 'Mpumalanga', 'NC' => 'Northern Cape', 'NW' => 'North West', 'WC' => 'Western Cape',
    ],
    'US' => [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
        'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida',
        'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana',
        'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
        'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
        'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire',
        'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota',
        'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
        'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
        'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
    ],
    'CA' => [
        'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba', 'NB' => 'New Brunswick', 'NL' => 'Newfoundland and Labrador',
        'NT' => 'Northwest Territories', 'NS' => 'Nova Scotia', 'NU' => 'Nunavut', 'ON' => 'Ontario', 'PE' => 'Prince Edward Island',
        'QC' => 'Quebec', 'SK' => 'Saskatchewan', 'YT' => 'Yukon',
    ],
];
