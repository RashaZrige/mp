document.addEventListener('DOMContentLoaded', function () {

  var wrap = document.querySelector('.hero-right');
  var hero = document.querySelector('.hero-img');
  var fi1  = document.querySelector('.fi1 img');
  var fi2  = document.querySelector('.fi2 img');
  var fi3  = document.querySelector('.fi3 img');

  function safe(src){ return encodeURI(src); }

 
 
  var slides = [
    {
      hero: '../image/pngegg - 2022-12-31T123205 1.png',
      icons: ['../image/photo_2025-08-22_14-45-16.jpg',
        '../image/Rectangle 12.png',
        '../image/Rectangle 13.png']
    },
    {
      hero: '../image/man2.png',
      icons: ['../image/plum-logo3.png','../image/electrocal-logo2.png','../image/electrocal-logo1.png']
    },
    {
      hero: '../image/happy-male-plumber-holding-monkey-wrench-sink-pipe-removebg-preview 1(1)_cut.png',
      icons: ['../image/Rectangle 27_cut.png','../image/plumbing 1_cut.png','../image/plumber (1) 1_cut.png']
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












