// Copy to clipboard + toast
window.toast = function(msg){
  const t = document.getElementById('toast');
  if(!t) return;
  t.textContent = msg;
  t.hidden = false;
  t.classList.add('show');
  setTimeout(()=> t.classList.remove('show'), 1800);
};

window.copy = function(txt){
  if(navigator.clipboard && navigator.clipboard.writeText){
    navigator.clipboard.writeText(txt).then(()=> toast('Copiado al portapapeles'));
  }else{
    // Fallback mínimo
    const ta = document.createElement('textarea');
    ta.value = txt; document.body.appendChild(ta); ta.select();
    try{ document.execCommand('copy'); toast('Copiado al portapapeles'); }catch(e){}
    ta.remove();
  }
};

// Auto-loading en botones con data-loading
document.addEventListener('submit', (ev) => {
  const form = ev.target;
  const submitter = form.querySelector('button[type="submit"][data-loading]');
  if(submitter && !submitter.disabled){
    submitter.classList.add('is-loading');
    // preserva el texto y añade spinner
    if(!submitter.dataset.label){
      submitter.dataset.label = submitter.innerHTML;
      submitter.innerHTML = `<span class="spinner" aria-hidden="true"></span><span>${submitter.textContent}</span>`;
    }
    submitter.disabled = true;
  }
}, true);
