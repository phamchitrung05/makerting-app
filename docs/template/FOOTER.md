# FOOTER.md — Đặc tả thiết kế FOOTER PaperNest cho Google Stitch

## 1. Phạm vi

Tạo **riêng phần cuối trang** của trang chủ PaperNest gồm **2 dải liên tục**:

1. Dải đăng ký nhận tin (newsletter) nền xanh mint nhạt.
2. Footer chính nền xanh navy đậm.

Dựa sát ảnh tham chiếu người dùng cung cấp. **Không dựng header, hero, danh mục sản phẩm hay testimonial**. Testimonial nằm ở cuối BODY, ngay trên dải newsletter này.

## 2. Định dạng chung

- Khung desktop thiết kế: rộng 1440 px, vùng content `max-width: 1320px` căn giữa, padding ngang 40–48 px.
- Typography: `Nunito Sans` / `Nunito`, thân thiện, nét rõ, đúng tính cách website stationery.
- Footer phải tạo cảm giác **điểm kết thúc trang rõ ràng**, tương phản so với body nền trắng nhưng vẫn cùng hệ pastel dễ thương.
- Không dùng họa tiết hình sóng, khối 3D, ảnh nền stock hoặc gradient chói.

## 3. Dải 1 — Newsletter “Join the PaperNest Family”

### Kích thước và màu

- Trải toàn chiều ngang viewport; chiều cao desktop khoảng **105–115 px**.
- Nền xanh mint/aqua rất sáng: `#BDF2EF` hoặc `#C5F4F1` (điều chỉnh theo ảnh mẫu).
- Không tách thành card; đây là **dải màu liên tục** ngay sau testimonial.

### Bố cục 3 vùng trên cùng một hàng

**Vùng trái (~12%)**
- Hình máy bay giấy nét outline xanh navy cỡ 50–65 px, có vệt bay nét đứt uốn cong. Đây là motif trang trí, không phải nút.

**Vùng giữa trái (~43%)**
- Heading `Join the PaperNest Family` màu navy đậm, chữ đậm, cỡ ~20–22 px.
- Dòng phụ bên dưới: `Get exclusive deals, new arrivals and creative inspiration.` cỡ ~12–13 px.
- Căn trái, hai dòng sát nhau, không chiếm quá nhiều chiều cao.

**Vùng giữa phải (~36%)**
- Một form nằm ngang gồm input trắng và nút tím bên phải.
- Placeholder input: `Enter your email address`.
- Input cao ~42 px, nền trắng, bo nhẹ 6 px, không viền dày.
- Nút tím `#69359B`, chữ trắng `Subscribe`, bo 6 px, ngang khoảng 145–165 px.
- Input và nút chạm liền hoặc có khoảng cách rất nhỏ, cân với phần text.

**Vùng phải (~9%)**
- Hand lettering nghiêng `Good Ideas Ahead` màu xanh navy, tim nhỏ phác tay ở dưới; giống nhãn vẽ tay trong ảnh.
- Không thay bằng icon chuông, icon phong bì hoặc hình người.

### Hành vi

- Nhập email, kiểm tra email hợp lệ, nhấn `Subscribe`.
- Thông báo thành công nhỏ gọn, không làm thay đổi chiều cao dải quá nhiều.
- Thiết kế nhìn giống ảnh ở trạng thái chưa nhập email.

## 4. Dải 2 — Footer xanh navy đậm

### Nền và khoảng cách

- Nền `#172841` hoặc `#15263F` trải toàn chiều ngang.
- Chữ chính trắng `#FFFFFF`, chữ phụ trắng hơi mờ `#CBD5E0`.
- Padding trên khoảng 28–34 px, dưới khoảng 28–36 px.
- Dựng thành bố cục nhiều cột vừa đủ, **không biến footer thành một khối rất cao**.

### Bố cục 5 khu vực desktop

Vùng nội dung chia theo tỷ lệ gợi ý:
- Cột 1 logo / mô tả / social: **28%**.
- Cột 2 Shop: **15%**.
- Cột 3 Help: **16%**.
- Cột 4 About: **17%**.
- Cột 5 slogan / bản quyền: **24%**.

Khoảng cách cột 24–36 px. Tất cả cột bắt đầu gần cùng cao độ.

### Cột 1 — PaperNest

- Logo nhiều màu giống header, chữ `PaperNest` **màu trắng** (không navy vì nền tối).
- Dưới chữ có tagline nhỏ `Write · Create · Belong`.
- Mô tả 2–3 dòng màu trắng ngà: `Premium stationery for brighter minds and more creative days.`
- Dòng biểu tượng mạng xã hội nhỏ ngang nhau: Instagram, Facebook, YouTube, Pinterest, TikTok.
- Icon social màu trắng/xám nhạt, không đặt trong vòng tròn quá lớn.

### Cột 2 — Shop

Heading `Shop` trắng, đậm cỡ ~14 px.

Các link xếp dọc, cỡ ~12–13 px, line-height khoảng 1.8:
- `All Products`
- `New Arrivals`
- `Best Sellers`
- `Seasonal Bundles`
- `Gift Cards`

### Cột 3 — Help

Heading `Help`.

- `Track Order`
- `Returns & Exchanges`
- `Shipping Info`
- `FAQs`
- `Contact Us`

### Cột 4 — About

Heading `About`.

- `Our Story`
- `Sustainability`
- `Blog`
- `Careers`
- `Partner With Us`

### Cột 5 — Slogan & legal

- Ở phần trên, slogan **viết tay màu trắng/hồng** căn tương đối giữa cột:
  `Bright Ideas`  
  `Never End` kèm trái tim màu hồng nhỏ.
- Có đường kẻ ngang mảnh màu trắng mờ ở bên dưới slogan, không quá dài.
- Phần dưới có bản quyền: `© 2026 PaperNest. All rights reserved.`
- Dòng link nhỏ tiếp theo: `Privacy` · `Terms` · `Accessibility`.
- Các link pháp lý cần dễ đọc nhưng nhỏ hơn menu footer.

## 5. Tỷ lệ thị giác cần đạt

- Newsletter sáng, thấp, mang tính CTA kết thúc.
- Footer navy tối, gọn; bên trái logo nhận diện rõ, giữa có **3 cột link**, bên phải là quote trang trí và legal.
- Phần newsletter và footer phải chạm nhau không có khoảng trắng tách rời.
- Không thêm một footer thứ ba hay dải logo đối tác.
- Không dùng layout dạng accordion ở desktop.

## 6. Responsive

- Tablet: newsletter chia text và form thành 2 vùng; motif máy bay giấy và chữ viết tay có thể thu nhỏ. Footer đổi thành 3–4 cột hoặc 2 hàng rõ ràng.
- Mobile: newsletter xếp dọc (heading, mô tả, input, nút); giấu bớt hình trang trí nếu thiếu không gian. Footer: logo và giới thiệu trước, các nhóm link chia 2 cột hoặc accordion, slogan và bản quyền cuối cùng.
- Giữ khoảng cách chạm tối thiểu 44 px cho các điều khiển chính trên mobile.

## 7. Những điều Stitch tuyệt đối không được làm

- Không đặt newsletter trong card nổi ở giữa trang.
- Không đổi newsletter thành nền tím hay cam.
- Không gộp 3 cột `Shop`, `Help`, `About` làm một danh sách duy nhất.
- Không thay footer tối navy bằng footer trắng.
- Không thêm địa chỉ, điện thoại, bản đồ, tải app hoặc logo thanh toán không có trong ảnh tham chiếu.
- Không dịch nội dung UI sang tiếng Việt (chỉ mô tả trong file Markdown là tiếng Việt).
- Không tự dựng testimonial trong footer vì testimonial thuộc BODY.

## 8. Tiêu chí nghiệm thu

1. Nhìn từ trên xuống thấy **dải mint đăng ký email rồi đến footer navy**.
2. Dải mint có máy bay giấy, tiêu đề, mô tả, input + nút Subscribe và chữ vẽ tay bên phải.
3. Footer navy có đúng 5 vùng desktop: brand, Shop, Help, About, slogan/legal.
4. Các nội dung đúng thứ tự và đúng từ ngữ như mô tả.
5. Tỷ lệ tổng thể gần ảnh gốc, không làm footer cao quá mức.

## 9. Prompt cuối dành cho Stitch

> Hãy dựng CHỈ FOOTER PaperNest như ảnh tham chiếu. Footer gồm một dải newsletter xanh mint sáng với hình máy bay giấy line art bên trái, heading “Join the PaperNest Family”, input email và nút tím Subscribe, chữ “Good Ideas Ahead” viết tay bên phải; ngay bên dưới là footer navy đậm có logo PaperNest và social bên trái, 3 cột link Shop–Help–About ở giữa, câu “Bright Ideas Never End” và copyright ở bên phải. Bám sát tỷ lệ, màu sắc và copy tiếng Anh trong ảnh mẫu. Không sáng tạo thêm section, không làm kiểu footer SaaS, không dựng body/header.
