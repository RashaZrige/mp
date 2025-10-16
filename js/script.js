


document.addEventListener('DOMContentLoaded', function () {

  var wrap = document.querySelector('.hero-right');
  var hero = document.querySelector('.hero-img');
  var fi1  = document.querySelector('.fi1 img');
  var fi2  = document.querySelector('.fi2 img');
  var fi3  = document.querySelector('.fi3 img');

  function safe(src){ return encodeURI(src); }

 
  var slides = [
    {
      hero: 'image/pngegg - 2022-12-31T123205 1.png',
      icons: ['image/Rectangle 12.png','image/Rectangle 13.png','image/photo_2025-08-22_14-45-16.jpg']
    },
    {
      hero: 'image/man2.png',
      icons: ['image/plum-logo3.png','image/electrocal-logo2.png','image/electrocal-logo1.png']
    },
    {
      hero: 'image/man 3.png',
      icons: ['image/electrocal-logo3.png','image/plum-logo1.png','image/plum-logo2.png']
    }
  ];

 
  var ok = slides.every(function(s){ return s && s.hero && Array.isArray(s.icons) && s.icons.length === 3; });
  if (!ok) {
    console.error('[Slider] كل شريحة لازم hero + 3 icons');
    return;
  }


  if (!document.querySelector('#hero-fade-style')) {
    var st = document.createElement('style');
    st.id = 'hero-fade-style';
    st.textContent = '.hero-right{transition:opacity .45s ease}.hero-right.fading{opacity:0}';
    document.head.appendChild(st);
  }

 
  slides.forEach(function(s){
    var im = new Image(); im.src = safe(s.hero);
    s.icons.forEach(function(ic){ var im2=new Image(); im2.src = safe(ic); });
  });

  var idx = 0;
  var INTERVAL = 3000;

  function setSlide(i){
    var s = slides[i];
    wrap.classList.add('fading');
    setTimeout(function(){
      hero.src = safe(s.hero);
      fi1.src  = safe(s.icons[0]);
      fi2.src  = safe(s.icons[1]);
      fi3.src  = safe(s.icons[2]);
      requestAnimationFrame(function(){ wrap.classList.remove('fading'); });
    }, 300);
  }


  setSlide(0);

 
  var loop = setInterval(function(){
    idx = (idx+1) % slides.length;
    setSlide(idx);
  }, INTERVAL);


  document.addEventListener('visibilitychange', function(){
    if (document.hidden) {
      clearInterval(loop);
    } else {
      loop = setInterval(function(){
        idx = (idx+1) % slides.length;
        setSlide(idx);
      }, INTERVAL);
    }
  });
});



document.addEventListener("DOMContentLoaded", () => {
  const items = document.querySelectorAll(".media-item");

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add("show");
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.2 }); 

  items.forEach(item => observer.observe(item));
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




document.addEventListener("DOMContentLoaded", function () {
  const slides = document.querySelectorAll(".discount-image .slide");
  let index = 0;

  setInterval(() => {
    slides[index].classList.remove("active");
    index = (index + 1) % slides.length;
    slides[index].classList.add("active");
  }, 3000);
});



