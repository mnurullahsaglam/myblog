<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attribute kabul edilmelidir.',
    'accepted_if' => ':attribute, :other :value olduğunda kabul edilmelidir.',
    'active_url' => ':attribute geçerli bir URL olmalıdır.',
    'after' => ':attribute, :date tarihinden sonra olmalıdır.',
    'after_or_equal' => ':attribute, :date tarihinden sonra veya aynı tarihte olmalıdır.',
    'alpha' => ':attribute sadece harf içerebilir.',
    'alpha_dash' => ':attribute sadece harf, rakam ve tire içerebilir.',
    'alpha_num' => ':attribute sadece harf ve rakam içerebilir.',
    'array' => ':attribute dizi olmalıdır.',
    'ascii' => ':attribute sadece ASCII karakterler içerebilir.',
    'before' => ':attribute, :date tarihinden önce olmalıdır.',
    'before_or_equal' => ':attribute, :date tarihinden önce veya aynı tarihte olmalıdır.',
    'between' => [
        'array' => ':attribute, :min - :max arasında öğe içermelidir.',
        'file' => ':attribute, :min - :max kilobayt arasında olmalıdır.',
        'numeric' => ':attribute, :min - :max arasında olmalıdır.',
        'string' => ':attribute, :min - :max karakter arasında olmalıdır.',
    ],
    'boolean' => ':attribute alanı doğru veya yanlış olmalıdır.',
    'can' => ':attribute için geçerli değil.',
    'confirmed' => ':attribute doğrulaması eşleşmiyor.',
    'contains' => ':attribute, şunlardan birini içermelidir: :values.',
    'current_password' => 'Mevcut parola yanlış.',
    'date' => ':attribute geçerli bir tarih olmalıdır.',
    'date_equals' => ':attribute, :date tarihine eşit olmalıdır.',
    'date_format' => ':attribute, :format biçimiyle eşleşmiyor.',
    'decimal' => ':attribute, :decimals ondalık basamağa sahip olmalıdır.',
    'declined' => ':attribute kabul edilmemelidir.',
    'declined_if' => ':attribute, :other :value olduğunda kabul edilmemelidir.',
    'different' => ':attribute ve :other farklı olmalıdır.',
    'digits' => ':attribute, :digits basamaklı olmalıdır.',
    'digits_between' => ':attribute, :min ile :max arasında basamaklı olmalıdır.',
    'dimensions' => ':attribute geçersiz görsel boyutlarına sahip.',
    'distinct' => ':attribute alanı yinelenen bir değere sahip.',
    'doesnt_end_with' => ':attribute, şu değerlerden biriyle bitmemelidir: :values.',
    'doesnt_start_with' => ':attribute, şu değerlerden biriyle başlamamalıdır: :values.',
    'email' => ':attribute geçerli bir e-posta adresi olmalıdır.',
    'ends_with' => ':attribute, şu değerlerden biriyle bitmelidir: :values.',
    'enum' => ':attribute geçerli bir değer değil.',
    'exists' => 'Seçili :attribute geçersiz.',
    'extensions' => ':attribute, şu uzantılardan birine sahip olmalıdır: :values.',
    'file' => ':attribute dosya olmalıdır.',
    'filled' => ':attribute alanı doldurulmuş olmalıdır.',
    'gt' => [
        'array' => ':attribute, :value öğeden fazla olmalıdır.',
        'file' => ':attribute, :value kilobayttan büyük olmalıdır.',
        'numeric' => ':attribute, :value sayısından büyük olmalıdır.',
        'string' => ':attribute, :value karakterden fazla olmalıdır.',
    ],
    'gte' => [
        'array' => ':attribute, :value öğeden fazla veya eşit olmalıdır.',
        'file' => ':attribute, :value kilobayttan büyük veya eşit olmalıdır.',
        'numeric' => ':attribute, :value sayısından büyük veya eşit olmalıdır.',
        'string' => ':attribute, :value karakterden fazla veya eşit olmalıdır.',
    ],
    'hex_color' => ':attribute geçerli bir HEX renk kodu olmalıdır.',
    'image' => ':attribute görsel olmalıdır.',
    'in' => 'Seçili :attribute geçersiz.',
    'in_array' => ':attribute alanı, :other içinden bir değere sahip olmalıdır.',
    'integer' => ':attribute bir tam sayı olmalıdır.',
    'ip' => ':attribute geçerli bir IP adresi olmalıdır.',
    'ipv4' => ':attribute geçerli bir IPv4 adresi olmalıdır.',
    'ipv6' => ':attribute geçerli bir IPv6 adresi olmalıdır.',
    'json' => ':attribute geçerli bir JSON dizesi olmalıdır.',
    'list' => ':attribute geçerli bir liste olmalıdır.',
    'lowercase' => ':attribute sadece küçük harf içerebilir.',
    'lt' => [
        'array' => ':attribute, :value öğeden az olmalıdır.',
        'file' => ':attribute, :value kilobayttan küçük olmalıdır.',
        'numeric' => ':attribute, :value sayısından küçük olmalıdır.',
        'string' => ':attribute, :value karakterden az olmalıdır.',
    ],
    'lte' => [
        'array' => ':attribute, :value öğeden az veya eşit olmalıdır.',
        'file' => ':attribute, :value kilobayttan küçük veya eşit olmalıdır.',
        'numeric' => ':attribute, :value sayısından küçük veya eşit olmalıdır.',
        'string' => ':attribute, :value karakterden az veya eşit olmalıdır.',
    ],
    'mac_address' => ':attribute geçerli bir MAC adresi olmalıdır.',
    'max' => [
        'array' => ':attribute, :max öğeden fazla olmamalıdır.',
        'file' => ':attribute, :max kilobayttan büyük olmamalıdır.',
        'numeric' => ':attribute, :max sayısından büyük olmamalıdır.',
        'string' => ':attribute, :max karakterden fazla olmamalıdır.',
    ],
    'max_digits' => ':attribute, :max basamaklı olmalıdır.',
    'mimes' => ':attribute, :values tipinde bir dosya olmalıdır.',
    'mimetypes' => ':attribute, :values tipinde bir dosya olmalıdır.',
    'min' => [
        'array' => ':attribute, en az :min öğe içermelidir.',
        'file' => ':attribute, en az :min kilobayt olmalıdır.',
        'numeric' => ':attribute, en az :min olmalıdır.',
        'string' => ':attribute, en az :min karakter olmalıdır.',
    ],
    'min_digits' => ':attribute, en az :min basamaklı olmalıdır.',
    'missing' => ':attribute alanı eksik olmalıdır.',
    'missing_if' => ':attribute alanı, :other :value olduğunda eksik olmalıdır.',
    'missing_unless' => ':attribute alanı, :other :values içinde olmadığında eksik olmalıdır.',
    'missing_with' => ':attribute alanı, :values mevcut olduğunda eksik olmalıdır.',
    'missing_with_all' => ':attribute alanı, :values mevcut olduğunda eksik olmalıdır.',
    'multiple_of' => ':attribute, :value katı olmalıdır.',
    'not_in' => 'Seçili :attribute geçersiz.',
    'not_regex' => ':attribute biçimi geçersiz.',
    'numeric' => ':attribute sayı olmalıdır.',
    'password' => [
        'letters' => ':attribute en az bir harf içermelidir.',
        'mixed' => ':attribute en az bir büyük harf ve bir küçük harf içermelidir.',
        'numbers' => ':attribute en az bir rakam içermelidir.',
        'symbols' => ':attribute en az bir sembol içermelidir.',
        'uncompromised' => ':attribute güvenli değil.',
    ],
    'present' => ':attribute alanı mevcut olmalıdır.',
    'present_if' => ':attribute alanı, :other :value olduğunda mevcut olmalıdır.',
    'present_unless' => ':attribute alanı, :other :values içinde olmadığında mevcut olmalıdır.',
    'present_with' => ':attribute alanı, :values mevcut olduğunda mevcut olmalıdır.',
    'present_with_all' => ':attribute alanı, :values mevcut olduğunda mevcut olmalıdır.',
    'prohibited' => ':attribute alanı yasaktır.',
    'prohibited_if' => ':attribute alanı, :other :value olduğunda yasaktır.',
    'prohibited_unless' => ':attribute alanı, :other :values içinde olmadığında yasaktır.',
    'prohibits' => ':attribute, :other alanı mevcut olduğunda yasaktır.',
    'regex' => ':attribute biçimi geçersiz.',
    'required' => ':attribute alanı gereklidir.',
    'required_array_keys' => ':attribute, :keys anahtarları gereklidir.',
    'required_if' => ':attribute alanı, :other :value olduğunda gereklidir.',
    'required_if_accepted' => ':attribute alanı, :other kabul edildiğinde gereklidir.',
    'required_if_declined' => ':attribute alanı, :other reddedildiğinde gereklidir.',
    'required_unless' => ':attribute alanı, :other :values içinde olmadığında gereklidir.',
    'required_with' => ':values mevcut olduğunda :attribute alanı gereklidir.',
    'required_with_all' => ':values mevcut olduğunda :attribute alanı gereklidir.',
    'required_without' => ':values mevcut olmadığında :attribute alanı gereklidir.',
    'required_without_all' => ':values mevcut olmadığında :attribute alanı gereklidir.',
    'same' => ':attribute ve :other eşleşmelidir.',
    'size' => [
        'array' => ':attribute, :size öğeye sahip olmalıdır.',
        'file' => ':attribute, :size kilobayt olmalıdır.',
        'numeric' => ':attribute, :size olmalıdır.',
        'string' => ':attribute, :size karakter olmalıdır.',
    ],
    'starts_with' => ':attribute, şu değerlerden biriyle başlamalıdır: :values.',
    'string' => ':attribute metin olmalıdır.',
    'timezone' => ':attribute geçerli bir saat dilimi olmalıdır.',
    'unique' => ':attribute zaten alınmış.',
    'uploaded' => ':attribute yüklemesi başarısız.',
    'uppercase' => ':attribute sadece büyük harf içerebilir.',
    'url' => ':attribute geçerli bir URL olmalıdır.',
    'ulid' => ':attribute geçerli bir ULID olmalıdır.',
    'uuid' => ':attribute geçerli bir UUID olmalıdır.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'name' => 'İsim',
        'email' => 'E-posta',
        'password' => 'Parola',
        'current_password' => 'Mevcut Parola',
        'new_password' => 'Yeni Parola',
        'new_password_confirmation' => 'Yeni Parola Doğrulama',
        'first_name' => 'Ad',
        'last_name' => 'Soyad',
        'photo' => 'Fotoğraf',
        'title' => 'Başlık',
        'content' => 'İçerik',
        'description' => 'Açıklama',
        'excerpt' => 'Alıntı',
        'date' => 'Tarih',
        'time' => 'Zaman',
        'available' => 'Müsait',
        'size' => 'Boyut',
        'birth_date' => 'Doğum Tarihi',
        'death_date' => 'Ölüm Tarihi',
        'birth_place' => 'Doğum Yeri',
        'death_place' => 'Ölüm Yeri',
    ],

];
