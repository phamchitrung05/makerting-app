/*
  =====================================================================
  CHỨC NĂNG FILE: Kiểm thử frontend cho bảo toàn đoạn, bullet và DNA.
  =====================================================================

  CÁC HÀM/COMPUTED/WATCHER TRONG FILE:
  - test giữ đoạn/bullet khi chuyển HTML bài mẫu.
  - test giữ DNA không sửa và xóa rule bằng thay đổi tường minh.
  - Computed: Không có.
  - Watcher/lifecycle: Không có.

  INPUT/OUTPUT CỦA FILE (tổng thể):
  - INPUT : Payload từ component hoặc action gọi service.
  - OUTPUT: Giá trị trả về từ helper/API hoặc cập nhật state Pinia.
  =====================================================================
*/
import assert from 'node:assert/strict'
import { test } from 'node:test'
import { htmlToText, mergeRuleEdits, textToHtml } from '../../resources/js/utils/sampleText.js'

test('sample keeps paragraph boundaries and bullets', () => {
  assert.equal(htmlToText('<p>Câu mở đầu</p><ul><li><p>Ý 1</p></li><li><p>Ý 2</p></li></ul>'), 'Câu mở đầu\n\n• Ý 1\n\n• Ý 2')
  assert.equal(htmlToText(textToHtml('Dòng < đầu\nDòng & sau')), 'Dòng < đầu\nDòng & sau')
})

test('saving an untouched form retains the exact DNA, and clearing is explicit', () => {
  const original = { pronouns: 'Xưng nhà em; gọi dâu rể', avoid: 'Bỏ từ lóng.', transitions: 'Nối ý bằng câu hỏi' }
  const form = { pronouns: 'Xưng nhà em, gọi dâu rể', avoid: 'Bỏ từ lóng', opening: '' }
  assert.deepEqual(mergeRuleEdits(original, form, form), original)
  assert.deepEqual(mergeRuleEdits(original, form, { ...form, avoid: '' }), {
    pronouns: original.pronouns,
    transitions: original.transitions,
  })
})
