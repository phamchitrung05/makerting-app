# BODY.md — Đặc tả thiết kế BODY trang chủ PaperNest cho Google Stitch

## 1. Mục tiêu và phạm vi

Dựng **riêng phần BODY** (phần nội dung giữa header và footer) của trang chủ PaperNest, dựa trên ảnh người dùng cung cấp. Đây là website bán văn phòng phẩm, đồ dùng học tập, đồ thủ công, quà tặng. Hướng thiết kế chính: **ảnh sản phẩm thật có màu pastel + nền trang sạch + lưới sản phẩm dày nhưng không rối + banner nhiều màu tươi**.

**Cực kỳ quan trọng:** bám sát cấu trúc và thứ tự của ảnh mẫu. Không chuyển website thành landing page SaaS, không đưa quá nhiều text, không thay các ảnh sản phẩm bằng gradient/shape trừ khi ảnh mẫu thực sự có nền gradient. **Chỉ tạo BODY**; không tạo lại header hoặc footer.

## 2. Khung trang, tỷ lệ và style toàn cục

- Canvas desktop mục tiêu: `1440px` rộng; nội dung chính `max-width: 1320px`, căn giữa, padding ngang 40–48 px.
- Nền body: trắng `#FFFFFF`, một số dải nền xám ấm rất nhạt `#F8F8FA`; khoảng trống giữa các khu vực 24–36 px desktop.
- Ảnh tham chiếu 736 × 1307 px là **ảnh thu nhỏ của trang desktop**, nên phải chuyển đổi tỷ lệ tương ứng khi thiết kế ở 1440 px.
- Card sản phẩm nhỏ gọn: ảnh chiếm **65–72% chiều cao card**, chữ và giá ở dưới; viền `#F0F0F2`, bo góc 6–8 px, shadow cực nhẹ.
- Font giao diện: `Nunito Sans` hoặc `Nunito`; tiêu đề khu vực đậm, navy `#14253E`; text phụ `#687083`.
- Brand purple `#69359B`; xanh ngọc `#1EB5AA`; vàng `#FFE27A`; hồng pastel `#F7C5DE`; mint `#C9F2E2`; xanh baby `#D0E8FF`. Badge sale đỏ san hô `#F05C5C`.
- Chỉ dùng **nội dung tiếng Anh như ảnh tham chiếu**. Tài liệu này bằng tiếng Việt, không dịch UI sang tiếng Việt.

## 3. Thứ tự section bắt buộc — giữ nguyên 100%

**BODY có 7 khu vực chính theo thứ tự từ trên xuống:**

1. Hero carousel rộng toàn màn hình
2. Hàng 8 danh mục hình tròn
3. Hai banner quảng bá đặt cạnh nhau
4. New Arrivals — 5 card
5. Best Sellers (4 card) song song với Seasonal Bundles (2 card)
6. Recommended for You (4 card) song song với Creative Inspiration (3 bài)
7. What Our Customers Say — 3 đánh giá

> Newsletter màu mint và footer xanh navy **không thuộc BODY**; chúng sẽ được mô tả trong `FOOTER.md`.

## 4. Section 1 — Hero Carousel hình ảnh lớn

### Bố cục

- Hero trải ngang **toàn viewport** ngay dưới menu, chiều cao desktop mục tiêu khoảng **410–460 px**.
- Dùng ảnh chụp sản phẩm văn phòng phẩm làm nền, tươi sáng và giàu chi tiết nhưng không gây rối.
- Bố cục ảnh: **bên trái là copy và CTA**, **giữa là cốc trắng đựng bút màu**, **bên phải là chồng sổ màu pastel + tờ giấy quote treo đứng**.
- Nền xanh trời pastel chuyển dần sang xanh mint, điểm nhấn màu hồng/vàng/tím. Bề mặt bàn màu hồng phấn, ánh sáng studio ban ngày, bóng đổ dịu.

### Copy chính xác

Tiêu đề nhiều dòng ở góc trái:

`Small`  
`Supplies`  
`Big Ideas`

- `Small Supplies`: kiểu chữ **đen/navy hơi nghiêng, tròn, rất đậm**, 2 dòng.
- `Big Ideas`: dòng lớn hơn, kiểu chữ hand-lettering/brush, phối **hồng / xanh / vàng** như ảnh.

Mô tả: `Everything you need to make your ideas happen.`

CTA: nút tím bo tròn hoàn toàn `Shop the Collection` + mũi tên phải trắng.

Dòng lợi ích nhỏ bên dưới vùng trái: 3 mục có icon line/hand-drawn `Study Better` · `Create More` · `Live Brighter`.

Các chi tiết sản phẩm trong ảnh:
- Cốc sứ trắng có câu `Good Ideas Start Here`.
- Chồng sách có chữ ngắn `PLAN`, `CREATE`, `ORGANIZE`, `ACHIEVE`, `BE HAPPY`.
- Tờ note treo bên phải có câu `A Brighter You Everyday` và trái tim phác nét.
- Vài chiếc kẹp giấy nhiều màu, bút, sticker rải tự nhiên trên mặt bàn.

### Carousel controls

- Chính giữa mép dưới hero có **3 chấm tròn**, chấm đầu màu navy đậm và hai chấm còn lại xám nhạt.
- Không thêm mũi tên lớn che ảnh.
- Không cần tạo 3 slide khác nhau khi bản đầu tiên được chụp; chỉ cần mô phỏng carousel với slide đầu.

### Cấm sai lệch

- Không dùng background hero chỉ toàn gradient và text; **bắt buộc có ảnh still-life sản phẩm**.
- Không đưa chữ giữa khung ảnh, không đổi CTA thành màu xanh.
- Không đổi thành bố cục split-screen 50/50 cứng nhắc; chữ phải nằm đè hợp lý lên vùng ảnh thoáng ở trái.

## 5. Section 2 — Hàng 8 danh mục tròn

- Nền trắng; padding trên/dưới khoảng 28–34 px.
- **8 mục ngang bằng nhau**, căn giữa. Mỗi icon minh họa được đặt trong một vòng tròn pastel đường kính khoảng **90–100 px** desktop.
- Icon có cảm giác sticker/3D nhẹ hoặc minh họa đồ dùng thật, màu tươi. Chữ nhãn màu navy, đậm, cỡ 13–14 px, căn giữa, có thể xuống 2 dòng.

| Thứ tự | Nhãn hiển thị | Hình minh họa | Nền vòng tròn |
|---|---|---|---|
| 1 | Notebooks & Journals | Sổ hồng | Hồng phấn |
| 2 | Pens & Writing | Cây bút | Vàng kem |
| 3 | Art & Craft | Bảng màu và cọ | Vàng nhạt |
| 4 | School Essentials | Ba lô xanh | Xanh baby |
| 5 | Office Supplies | Kẹp bướm đen | Mint |
| 6 | Planners & Organizers | Lịch hồng | Vàng kem |
| 7 | Stickers & Accessories | Trái tim hồng | Hồng nhạt |
| 8 | Gifts & Bundles | Hộp quà | Xanh mint |

- Không đổi thành card hình chữ nhật hoặc 4 × 2 khi ở desktop.

## 6. Section 3 — Hai banner quảng bá song song

- Hai banner rộng bằng nhau, chia **2 cột 50/50**, khoảng cách giữa cột 18–20 px.
- Tỉ lệ mỗi banner desktop gần **2.7:1**, hình chữ nhật ngang, bo góc 8–10 px.

### Banner trái — School Essentials

- Nền xanh mint pastel.
- Nội dung chữ ở **1/3 bên trái**, hình sản phẩm ở **2/3 bên phải**.
- Tiêu đề navy lớn, đậm, ngắt 3 dòng: `Make` / `Learning` / `More Fun`.
- Mô tả: `Colorful supplies for brighter minds.`
- CTA **vàng** dạng pill: `Shop School Essentials`.
- Hình: ba lô học sinh xanh lam/turquoise lớn, sổ lò xo vàng, hộp bút, bút chì màu; có quote nhỏ trang trí trên ba lô.

### Banner phải — Art & Craft

- Nền **hồng pastel** có chuyển động mực màu/cọ vẽ.
- Tiêu đề navy trái: `Create` / `Without` / `Limits`.
- Mô tả: `Art supplies for every imagination.`
- CTA **xanh ngọc** dạng pill: `Shop Art & Craft`.
- Phía phải: khay màu nước, các cây cọ, những nét quệt màu rực rỡ xanh–hồng–cam–tím.

Không thay bằng hình stock người mẫu. Không dùng nền một màu đơn điệu không có vật dụng.

## 7. Quy chuẩn hàng tiêu đề cho section bán hàng

- Tiêu đề chính navy `#14253E`, đậm, cỡ 24–28 px ở desktop.
- Subtitle nhỏ màu xám 12–14 px bên cạnh hoặc bên dưới (theo ảnh).
- Bên phải có link `View All →` màu xanh navy/tím, cỡ 13 px.
- Khoảng cách từ tiêu đề đến lưới card 12–16 px.
- Các dãy sản phẩm sát nhau hơn banner, cảm giác cửa hàng thương mại điện tử thực sự.

## 8. Section 4 — New Arrivals: 5 sản phẩm / một hàng

Tiêu đề: `New Arrivals`.
Subtitle: `Fresh picks for your creative journey`.
Bên phải: `View All →`.

Dựng **đúng 5 card ngang** ở desktop, cùng chiều cao:

| # | Tên | Giá | Hình chính |
|---|---|---|---|
| 1 | Pastel Spiral Notebook | $12.99 | Sổ lò xo màu hồng–xanh pastel |
| 2 | Gel Pen Set (12 Pcs) | $14.99 | 12 bút gel màu cầu vồng xếp thẳng |
| 3 | Aesthetic Sticky Notes | $6.99 | Các tập sticky note pastel vuông |
| 4 | Desk Organizer | $18.99 | Hộp để bút hồng gỗ/kem nhiều bút màu |
| 5 | Minimal Planner 2026 | $16.99 | Sổ planner xanh mint viền kem |

- Giá đậm màu navy. Đánh giá sao vàng `★★★★★` và số lượng review nhỏ xám ở dưới.
- Nút giỏ hàng hình vuông nhỏ **nền vàng kem** ở **góc phải vùng thông tin**, icon giỏ màu navy.
- Ảnh sản phẩm khung chữ nhật ở trên, nền chụp studio màu sáng.
- Không tạo card cao gấp đôi ảnh mẫu hoặc đưa mô tả sản phẩm nhiều dòng.

## 9. Section 5 — 2 cụm sản phẩm cùng một hàng

Tạo lưới cha gồm **cột trái 55%** và **cột phải 45%**, gap khoảng 24–28 px. Hai phần phải bắt đầu ở cùng cao độ.

### 9.1. Best Sellers — cột trái

- Heading `Best Sellers`, subtitle `Customer favorites, always in style`, link `View All →`.
- **4 card nhỏ** ngang nhau, gồm:
  1. `Fineliner Pen Set` — `$12.99` — bộ bút fineliner đen.
  2. `Hardcover Journal` — `$15.99` — sổ bìa cứng xanh navy có dây chun vàng.
  3. `Washi Tape Set` — `$9.99` — cuộn băng keo họa tiết pastel.
  4. `Marker Set (24 Pcs)` — `$24.99` — set bút marker nhiều màu.
- Sao vàng, lượt đánh giá nhỏ, nút giỏ vàng như phần New Arrivals.

### 9.2. Seasonal Bundles — cột phải

- Heading `Seasonal Bundles`, subtitle `More creativity, more value`, link `View All →`.
- **2 card lớn** ngang nhau:
  1. `Back to School Bundle`: ảnh combo sổ, bút màu, đồ học tập pastel; giá `$39.99`, giá gốc `$52.99` màu xám gạch ngang; badge `Save 25%` đỏ san hô góc ảnh.
  2. `Creative Artist Bundle`: ảnh combo cọ, sổ, dụng cụ hội họa; giá `$49.99`, giá gốc `$71.99` gạch ngang; badge `Save 30%` đỏ.
- Hai thẻ này ảnh lớn hơn card Best Sellers, nhưng **đáy card phải gần ngang nhau**.

## 10. Section 6 — Recommended for You và Creative Inspiration

Tiếp tục lưới **cột trái 55% / cột phải 45%**, gap 24–28 px.

### 10.1. Recommended for You — trái

- Heading `Recommended for You`.
- Subtitle `Picked just for your creative vibe`.
- Link `View All →`.
- Có **4 card ngang**:
  1. `Daily Planner` — `$14.99` — sổ planner hồng nhạt.
  2. `Highlighters` — `$8.99` — 3–4 bút dạ quang màu neon/pastel.
  3. `Sticker Pack` — `$6.99` — bộ sticker màu sắc dễ thương.
  4. `Pencil Case` — `$11.99` — túi bút màu mint có họa tiết.
- Ảnh + tên + giá + nút giỏ hàng nhỏ màu vàng; **không cần sao đánh giá cho dãy này** nếu ảnh mẫu không thể hiện.

### 10.2. Creative Inspiration — phải

- Heading `Creative Inspiration`, subtitle `Ideas, tips and stories`, link `View All →`.
- **3 bài viết đặt ngang**, mỗi bài có ảnh tỷ lệ khoảng 1.3–1.5:1, dưới ảnh là nhan đề tối đa 2 dòng.
  1. `5 Journaling Ideas for a Happier You` — ảnh viết nhật ký trên bàn giấy.
  2. `DIY Desk Decor on a Budget` — ảnh bàn làm việc có bút, hộp gỗ màu pastel.
  3. `Color Your Stress Away` — ảnh hoạt động tô màu vẽ minh họa.
- Các mục bài viết không có nút giỏ hàng hay giá.

## 11. Section 7 — Khách hàng đánh giá

- Dải nền xám rất nhạt `#F5F6FA` trải toàn viewport; nội dung căn giữa.
- Heading ở giữa: `What Our Customers Say` (navy, đậm).
- Subtitle: `Real people. Brighter stories.`
- Bên dưới **3 testimonial card** trắng, ngang nhau, gap 16–20 px, padding ~18–22 px, bo góc 8 px, không bóng đổ nặng.
- Mỗi card: avatar người thật **hình tròn ở trái**, phần phải có **5 sao vàng**, đoạn review ngắn và tên tác giả nhỏ.

Nội dung:
1. `“Amazing quality and super cute designs! I use PaperNest every day.”` — `Ayesha K.` — avatar nữ.
2. `“Fast delivery and fantastic products. My go-to stationery store!”` — `Bilal S.` — avatar nam.
3. `“Everything is so aesthetic and useful. Highly recommend!”` — `Sara M.` — avatar nữ.

- Thể hiện review gần như ảnh mẫu, tránh chừa khoảng trắng quá lớn dưới card.

## 12. Bắt buộc về hình ảnh và chất liệu

- Ưu tiên **ảnh chụp sản phẩm thật/studio** (product photography) cho hero, 2 promo banner, card sản phẩm và bài blog. Các vòng tròn danh mục dùng illustration/sticker.
- Ánh sáng mềm ban ngày, ảnh có chi tiết đồ dùng học tập và văn phòng phẩm, phông hồng–be–trắng–mint, bóng đổ nhẹ.
- Hình cần **đúng loại sản phẩm tương ứng từng card**. Không lặp một ảnh cho nhiều card; không dùng ảnh phong cảnh, người mẫu thời trang, điện thoại hoặc dashboard.
- Nếu Stitch không đọc được chữ nằm bên trong hình sản phẩm (trên sổ, cốc, quyển sách), ưu tiên đúng **bố cục và vật thể** hơn chữ trang trí nhỏ.
- Tránh ảnh có watermark, logo lạ, chữ vô nghĩa quá lớn.

## 13. Responsive cho BODY

| Khu vực | Desktop ≥1200px | Tablet 768–1199px | Mobile ≤767px |
|---|---|---|---|
| Hero | Một banner rộng, text trái | Giữ banner, giảm cỡ chữ | Crop ảnh khéo, chữ trái hoặc trên; CTA không che ảnh |
| Danh mục | 8 mục / hàng | 4 mục / hàng | Cuộn ngang hoặc 4 mục / hàng |
| Banner promo | 2 cột | 2 cột | 1 cột |
| New Arrivals | 5 card / hàng | 3 card / hàng | 2 card / hàng |
| Best Sellers / Bundles | 2 cụm cạnh nhau (4+2) | Xếp 2 cụm dọc | Xếp dọc, card 2 cột |
| Recommended / Inspiration | 2 cụm cạnh nhau (4+3) | Xếp 2 cụm dọc | Xếp dọc, cards 2 cột, blog 1–2 cột |
| Testimonials | 3 cột | 2 cột | 1 cột |

**Bản ưu tiên dựng trước là desktop 1440 px**, không được hi sinh bố cục desktop để tối ưu mobile.

## 14. Danh sách những lỗi Stitch cần tránh

- Không tự thêm thanh thông báo, header, newsletter hay footer vào BODY.
- Không gộp 5 sản phẩm New Arrivals thành 3 sản phẩm.
- Không làm Best Sellers và Seasonal Bundles thành 2 hàng riêng ở desktop.
- Không đưa cột blog Creative Inspiration xuống dưới Recommended ở desktop.
- Không biến danh mục tròn thành list item dài.
- Không chuyển màu nền toàn trang sang tím hay pastel; **nền chính phải trắng**.
- Không dùng card viền tím đậm, icon gradient, animation quá mức.
- Không viết lại copy/giá hoặc bịa thêm nội dung marketing không xuất hiện trong ảnh.

## 15. Prompt cuối dành cho Stitch

> Hãy dựng CHỈ BODY trang chủ PaperNest theo ảnh tham chiếu đính kèm, ở khung desktop 1440px. Phải giống thứ tự và tỷ lệ: (1) hero pastel có sản phẩm thật, copy “Small Supplies Big Ideas” bên trái; (2) hàng 8 danh mục hình tròn; (3) 2 banner hình ảnh song song mint và hồng; (4) New Arrivals 5 card; (5) Best Sellers 4 card ở trái cùng hàng với Seasonal Bundles 2 card ở phải; (6) Recommended for You 4 card trái cùng hàng với Creative Inspiration 3 bài bên phải; (7) 3 testimonial card trên nền xám nhạt. Giữ copy tiếng Anh, giá, số lượng card và màu nút như đặc tả. Ảnh chụp stationery thật, cảm giác pastel ấm và sạch. Không tạo header/footer, không tái sáng tạo bố cục. Ưu tiên độ giống ảnh mẫu hơn hiệu ứng hoặc trang trí tự do.
