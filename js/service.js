document.addEventListener("DOMContentLoaded", () => {
  const list = document.querySelector(".questions");
  if (!list) return;

  // ابدأ بإغلاق كل الإجابات وضبط الحالة
  const items = list.querySelectorAll(".question-details");
  items.forEach((item) => {
    const btn = item.querySelector(".q-btn");
    const ans = item.querySelector(".question-answer");
    const icon = item.querySelector(".icon");
    // if (!btn  !ans  !icon) return;

    btn.setAttribute("aria-expanded", "false");
    ans.hidden = true;
    icon.textContent = "+";
  });

  // تفويض أحداث: كليك على الزر فقط
  list.addEventListener("click", (ev) => {
    const btn = ev.target.closest(".q-btn");
    if (!btn) return;

    const item = btn.closest(".question-details");
    const ans = item.querySelector(".question-answer");
    const icon = item.querySelector(".icon");
    const isOpen = btn.getAttribute("aria-expanded") === "true";

    // اغلق الكل
    items.forEach((it) => {
      const b = it.querySelector(".q-btn");
      const a = it.querySelector(".question-answer");
      const ic = it.querySelector(".icon");
      if (b && a && ic) {
        b.setAttribute("aria-expanded", "false");
        a.hidden = true;
        ic.textContent = "+";
        it.classList.remove("open");
      }
    });

    // افتح الحالي لو كان مغلق
    if (!isOpen) {
      btn.setAttribute("aria-expanded", "true");
      ans.hidden = false;
      icon.textContent = "−";
      item.classList.add("open");
    }
  });
});












document.addEventListener('DOMContentLoaded', function () {

  const scope = document.querySelector('.testimonials');
  if (!scope) return;

  const dots   = scope.querySelectorAll('.dots .dot');


  const slides = scope.querySelectorAll('.cards[data-index]');

 
  if (dots.length !== slides.length) {
    console.warn('عدد النقاط لا يساوي عدد الشرائح:', dots.length, slides.length);
  }


  function activate(i){
    slides.forEach(s => s.classList.remove('is-active'));
    dots.forEach(d => d.classList.remove('active'));

    const slide = slides[i];
    const dot   = dots[i];
    if (!slide || !dot) return;

    slide.classList.add('is-active');
    dot.classList.add('active');
  }


  let start = 0;
  slides.forEach((s, idx) => {
    if (s.classList.contains('is-active')) start = idx;
  });
  activate(start);

  dots.forEach((dot, index) => {
    dot.addEventListener('click', () => activate(index));
  });
});
