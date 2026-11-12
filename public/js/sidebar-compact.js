(() => {
  const toggle=document.getElementById('sidebarToggle');if(!toggle)return;
  const key='rideutem.sidebar.minimised';let compact=false;
  try{compact=localStorage.getItem(key)==='true';}catch(_){}
  const render=()=>{
    document.body.classList.toggle('sidebar-minimised',compact);
    toggle.setAttribute('aria-expanded',String(!compact));
    toggle.setAttribute('aria-label',compact?'Expand sidebar':'Minimise sidebar');
    toggle.title=compact?'Expand sidebar':'Minimise sidebar';toggle.querySelector('span').textContent=compact?'»':'«';
  };
  toggle.hidden=false;render();
  toggle.addEventListener('click',()=>{compact=!compact;render();try{localStorage.setItem(key,String(compact));}catch(_){}window.dispatchEvent(new Event('resize'));});
})();
