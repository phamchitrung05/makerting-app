<?php
/**
 * =====================================================================
 * CHỨC NĂNG FILE: Bộ dữ liệu mẫu cho 20 ca đánh giá prompt V2/V3
 * =====================================================================
 * CÁC HÀM/METHOD TRONG FILE:
 * - Không có; file trả về mảng cấu hình hoặc fixture.
 * INPUT/OUTPUT CỦA FILE (tổng thể):
 * - INPUT : Cấu hình môi trường và các file tham chiếu khi có require.
 * - OUTPUT: array để provider hoặc bộ đánh giá sử dụng.
 * SIDE EFFECT: Nạp cấu hình trong bộ nhớ; không gọi AI hoặc ghi DB.
 * EXCEPTION/TRANSACTION: Lỗi nạp file hoặc JSON có thể truyền lên; không mở transaction.
 * =====================================================================
 */

return [
    'sales' => [
        'source' => "Dâu rể ơi, có ai chọn được hộp quà rồi mới nhớ chưa đặt tem không nè? Nhà em từng gặp câu này suốt.\n\n• Tem giấy nhám, 80.000đ mỗi bộ.\n• Nhận mẫu ở 12 Đường Mẫu Cũ.\n\nDâu rể cứ inbox nhà em xem mẫu trước nha.",
        'type' => 'sales_post',
        'tier' => 'short',
        'targets' => [
            ['Giới thiệu thiệp cưới', 'Có mẫu thiệp giấy kem', 'Inbox để xem mẫu'],
            ['Giới thiệu sổ ký tên', 'Có sổ bìa vải màu be', 'Inbox để chọn màu'],
            ['Giới thiệu bảng chào tiệc', 'Bảng có hai khổ A3 và A4', 'Inbox để hỏi kích thước'],
            ['Giới thiệu hộp quà cưới', 'Có hộp màu trắng ngà', 'Inbox để xem hộp'],
        ],
        'source_terms' => ['80.000đ', '12 Đường Mẫu Cũ'],
    ],
    'technical' => [
        'source' => "## Tại sao cần kiểm tra kích thước?\nKích thước đầu ra là thông số để đối chiếu với khuôn. Nếu hai thông số lệch nhau, phần hình nằm sát mép có thể bị cắt.\n\n## Cách đối chiếu\nĐọc chiều ngang và chiều cao từ tệp xuất. So lại từng chiều với bản thiết kế. Khi có sai lệch, kiểm tra đơn vị đo trước khi chỉnh tỷ lệ.",
        'type' => 'technical_article',
        'tier' => 'medium',
        'targets' => [
            ['Kiểm tra file trước in', 'File phải ghi rõ đơn vị mm', 'Đối chiếu đơn vị trước khi gửi'],
            ['Kiểm tra thứ tự trang', 'Bản mẫu gồm 8 trang', 'So thứ tự trang với bản mẫu'],
            ['Đặt tên bản xuất', 'Tên file có mã đơn và số phiên bản', 'Kiểm tra mã đơn trên tên file'],
            ['Chọn khổ tờ thử', 'Khổ thử là A4', 'Xác nhận khổ giấy trước khi in'],
        ],
    ],
    'announcement' => [
        'source' => 'Thông báo nhận mẫu in vào thứ Sáu. Khách lấy mẫu tại quầy số 2 và cung cấp mã đơn. Nếu cần đổi giờ, nhắn lại cho cửa hàng trước khi đến.',
        'type' => 'announcement',
        'tier' => 'micro',
        'targets' => [
            ['Thông báo đổi quầy', 'Quầy nhận hàng chuyển sang quầy số 3', 'Mang theo mã đơn'],
            ['Thông báo nghỉ sáng', 'Cửa hàng mở lại lúc 13 giờ', 'Nhắn trước khi đến'],
            ['Thông báo gửi bản xem', 'Bản xem được gửi qua email', 'Kiểm tra hộp thư'],
            ['Thông báo đổi lịch', 'Buổi duyệt mẫu chuyển sang thứ Ba', 'Xác nhận lại lịch'],
        ],
        'source_terms' => ['thứ Sáu', 'quầy số 2'],
    ],
    'storytelling' => [
        'source' => "Lúc mở ngăn kéo, tôi thấy lại mảnh giấy mình cất từ mùa trước. Nếp gấp ở góc đã mềm. Tôi đọc chậm từng dòng rồi mới nhớ vì sao hôm ấy tôi đã viết vội.\n\nChuyện nhỏ thôi. Trên đường về, tôi quyết định gọi cho người đã đưa mình mảnh giấy. Chúng tôi nói chuyện một lát, đủ để thấy thời gian không làm mất hết những điều mình từng để ý.",
        'type' => 'storytelling',
        'tier' => 'long',
        'targets' => [
            ['Kể về buổi nhận thiệp đầu tiên', 'Người kể được cầm mẫu thiệp giấy nhám', 'Mời người đọc chia sẻ kỷ niệm'],
            ['Kể về buổi gói quà', 'Người kể dùng dây vải màu đỏ để gói quà', 'Mời kể một lần gói quà đáng nhớ'],
            ['Kể về cuốn sổ tay mới', 'Cuốn sổ có bìa xanh', 'Mời người đọc viết lại một điều nhỏ'],
            ['Kể về bàn làm việc cuối ngày', 'Trên bàn có một phong thư chưa mở', 'Mời người đọc nghĩ về cuối ngày của mình'],
        ],
        'source_terms' => ['mảnh giấy mình cất từ mùa trước'],
    ],
    'restrained' => [
        'source' => "Trên nền giấy trắng ngà, đường ép nhũ nằm ở góc dưới. Ánh sáng đi qua rất nhẹ, vừa đủ để thấy đường nét.\n\nBản thiết kế để một khoảng trống rộng quanh tên. Mỗi chi tiết đều có chỗ riêng, và người đọc có thể dừng ở đó lâu hơn một chút.",
        'type' => 'sales_post',
        'tier' => 'short',
        'targets' => [
            ['Giới thiệu hộp đựng trang sức', 'Hộp lót vải mịn màu kem', 'Xem mẫu tại cửa hàng'],
            ['Giới thiệu túi giấy', 'Túi có quai vải màu nâu', 'Nhắn để xem mẫu'],
            ['Giới thiệu nhãn ly', 'Nhãn in một màu trên nền trong', 'Nhắn để chọn kích thước'],
            ['Giới thiệu danh thiếp', 'Danh thiếp có nền xanh đậm', 'Xem bản in thử'],
        ],
    ],
];
