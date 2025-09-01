document.addEventListener('touchstart', function (event) {
  if (event.touches.length > 1) {
    event.preventDefault();
  }
}, { passive: false });

document.addEventListener('gesturestart', function (event) {
  event.preventDefault();
});

document.documentElement.addEventListener('touchmove', function (event) {
  if (event.scale !== 1) {
    event.preventDefault();
  }
}, { passive: false });

document.getElementById('texture-content').addEventListener('touchmove', function(e) {
  e.stopPropagation();
}, { passive: true });

document.getElementById('texture-content').addEventListener('scroll', function(e) {
  e.stopPropagation();
}, { passive: true });
