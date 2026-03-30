<?php

return [
    'required' => ':attribute 필드는 필수입니다.',
    'string' => ':attribute은(는) 문자열이어야 합니다.',
    'integer' => ':attribute은(는) 정수여야 합니다.',
    'numeric' => ':attribute은(는) 숫자여야 합니다.',
    'email' => ':attribute은(는) 유효한 이메일 주소여야 합니다.',
    'max' => [
        'string' => ':attribute은(는) :max자를 초과할 수 없습니다.',
        'numeric' => ':attribute은(는) :max를 초과할 수 없습니다.',
    ],
    'min' => [
        'string' => ':attribute은(는) 최소 :min자여야 합니다.',
        'numeric' => ':attribute은(는) 최소 :min이어야 합니다.',
    ],
    'unique' => ':attribute은(는) 이미 사용 중입니다.',
    'exists' => '선택한 :attribute이(가) 잘못되었습니다.',
    'in' => '선택한 :attribute이(가) 잘못되었습니다.',
    'date' => ':attribute은(는) 유효한 날짜가 아닙니다.',
    'confirmed' => ':attribute 확인이 일치하지 않습니다.',
    'array' => ':attribute은(는) 배열이어야 합니다.',
];
