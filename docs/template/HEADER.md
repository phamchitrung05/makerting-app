# HEADER.md — Đặc tả thiết kế HEADER PaperNest cho Google Stitch

## 1. Vai trò và yêu cầu bắt buộc

Tạo **riêng phần HEADER** của trang chủ cửa hàng văn phòng phẩm **PaperNest**, tham chiếu trực tiếp **ảnh mẫu do người dùng tải lên**. Kết quả phải giống ảnh mẫu về thứ tự, tỷ lệ, màu sắc, độ thoáng và phân cấp thị giác; **không sáng tạo lại bố cục**. Đây là website thương mại điện tử desktop, phong cách trẻ trung, pastel, sáng, gọn gàng và có điểm nhấn tím.

**Chỉ dựng header** với 3 hàng nằm liên tiếp, không tạo hero, danh mục sản phẩm hay footer. Header trải hết chiều ngang màn hình, nội dung bên trong nằm cùng một lưới căn giữa với phần body.

## 2. Kích thước và lưới

- Thiết kế desktop mục tiêu: **1440 px**; kích thước ảnh tham chiếu là 736 × 1307 px, ảnh đã được thu nhỏ, vì vậy **giữ tỷ lệ thị giác khi phóng lên** chứ không lấy 736 px làm kích thước desktop.
- Vùng nội dung chính: `max-width: 1320px`, padding hai bên khoảng `40–48px` ở desktop; với màn nhỏ hơn dùng 24 px.
- Header gồm 3 hàng: thanh thông báo khoảng **40 px**, hàng logo–tìm kiếm–tiện ích khoảng **80–90 px**, hàng menu khoảng **48 px**. Tổng chiều cao xấp xỉ **168–178 px** trên desktop.
- Hàng nào cũng thẳng hàng, không bị tràn, không có bóng đổ nặng, không dùng bo tròn dạng “viên thuốc” cho cả header.

## 3. Hàng 1 — Thanh thông báo màu tím

**Nền:** tím đậm `#69359B` hoặc màu tím tương đương ảnh; **chữ trắng**, cỡ khoảng 12–13 px desktop.

Bố cục một hàng ngang:
- Khu vực giữa hơi lệch trái: biểu tượng xe giao hàng nhỏ → `Free Shipping on Orders Over $50` → dấu phân cách `|` → `Bright Ideas, Brighter Days!`.
- Góc phải: `Help` → gạch phân cách → `Track Order` → gạch phân cách → `USD` và mũi tên xuống nhỏ.
- Chỉ dùng chữ đơn giản; biểu tượng nhỏ, màu trắng, không dùng hình minh họa lớn.
- Duy trì màu tím xuyên suốt, không gradient rực rỡ.

## 4. Hàng 2 — Logo, thanh tìm kiếm, tiện ích

**Nền:** trắng `#FFFFFF`.

Bố cục trên một hàng gồm 3 vùng:

### 4.1. Logo bên trái (~24% chiều rộng)

- Logo gồm **hình nhiều cánh như lá/bút/giọt màu** phối hồng, cam, vàng, xanh lam, xanh lá ở trái và chữ `PaperNest` đậm màu xanh navy đậm ở phải.
- Dưới chữ `PaperNest` có tagline nhỏ: `Write · Create · Belong`.
- Logo phải trông như thương hiệu đồ dùng học tập thân thiện, không phải logo công nghệ hay logo tối giản một chữ cái.
- Nếu chưa có logo gốc, dựng **logo gần giống ảnh tham chiếu**, ưu tiên bố cục và sắc độ, không dùng emoji thay logo.

### 4.2. Tìm kiếm ở giữa (~48% chiều rộng)

- Thanh tìm kiếm nền xám rất nhạt `#F7F7F8`, viền xám `#E6E6EC`, bo góc nhẹ **5–7 px**, chiều cao ~44 px.
- Placeholder: `Search for notebooks, pens, art supplies...` màu xám vừa.
- Nút tìm kiếm tím `#69359B` đặt **dính sát bên phải trường tìm kiếm**, rộng ~56 px, icon kính lúp trắng; không tách rời thành nút cách xa thanh search.
- Trường tìm kiếm chiếm ưu thế trong hàng 2; hai vùng bên cân bằng.

### 4.3. Tiện ích bên phải (~28% chiều rộng)

Ba mục phân bố ngang đều, mỗi mục **icon nét đen/navy ở trên và nhãn nhỏ ở dưới**:
1. Icon người → `Account`
2. Icon trái tim viền → `Wishlist`
3. Icon giỏ hàng → `Cart`, trên góc icon có chấm tròn đỏ/hồng với số `0` màu trắng.

- Giữ icon stroke mảnh, không dùng icon dạng khối 3D.
- Kích thước icon xấp xỉ 25–28 px; nhãn 12 px.
- Không thêm nút đăng nhập màu tím hay avatar người dùng.

## 5. Hàng 3 — Menu điều hướng

- Nền trắng, đường phân cách phía dưới màu xám rất nhạt `#E9E9ED`.
- Các mục nằm trên **một hàng ngang**, căn đều, chữ màu navy `#18243B`, cỡ khoảng 13–14 px:
  `Home` · `Notebooks` · `Pens & Writing` · `Art & Craft` · `Office Supplies` · `School Essentials` · `Planners` · `Brands` · `Deals`.
- `Home` ở trạng thái active có **gạch chân màu tím/navy** sát đáy thanh menu (dày ~2 px).
- `Deals` có icon thẻ giá màu hồng/đỏ ở trước, chữ đỏ/hồng.
- Giữ thứ tự chính xác. Không dùng menu dạng thẻ card, không biến thành sidebar.

## 6. Ngôn ngữ giao diện và cảm giác hình ảnh

- Chữ nội dung giao diện là tiếng Anh **đúng như ảnh mẫu**; bản tài liệu hướng dẫn này bằng tiếng Việt.
- Font: ưu tiên `Nunito Sans` / `Nunito` hoặc sans-serif bo tròn thân thiện, tiêu đề logo đậm.
- Tông màu chính: tím `#69359B`, navy `#172640`, nền trắng, pastel hồng, xanh mint, xanh trời, vàng kem ở các phần bên dưới.
- Icon mềm mại, dễ đọc. Không sử dụng glassmorphism, neon, viền dày hoặc shadow tối.

## 7. Responsive

- **Tablet (768–1023 px):** logo bên trái, search co lại, tiện ích còn icon + nhãn ngắn; menu có thể cuộn ngang một hàng.
- **Mobile (≤767 px):** thanh thông báo rút gọn thành thông điệp chính; hàng chính gồm nút menu, logo, icon search và giỏ hàng. Hiển thị thanh search ở hàng riêng khi mở; menu chuyển thành drawer. Không cố nhét 9 mục điều hướng trên một hàng màn nhỏ.
- Responsive không được làm thay đổi bản desktop khi Stitch dựng hình mặc định.

## 8. Tiêu chí nghiệm thu

- Nhìn vào bản desktop phải thấy ngay **3 dải ngang** giống ảnh tham chiếu.
- Tím chỉ tập trung ở thanh thông báo và nút search; nền còn lại trắng.
- Logo trái, search lớn ở giữa, 3 tiện ích phải.
- Nav menu đủ 9 mục, đúng thứ tự, `Home` có gạch chân, `Deals` nổi đỏ.
- Không được thêm hero hoặc các section khác vào bản xuất của file này.

## 9. Prompt cuối dành cho Stitch

> Hãy dựng CHỈ HEADER của website PaperNest dựa trên ảnh đính kèm. Bám sát ảnh gốc theo từng hàng, tỷ lệ, màu và căn lề. Header desktop gồm thanh thông báo tím, hàng logo nhiều màu + thanh tìm kiếm lớn + Account/Wishlist/Cart, và thanh điều hướng 9 mục. Dùng copy tiếng Anh đúng ảnh. Thiết kế phẳng, nhẹ, sáng, chi tiết nhỏ gọn; không tái thiết kế, không thêm các thành phần khác. Ưu tiên fidelity với ảnh hơn sự sáng tạo.
