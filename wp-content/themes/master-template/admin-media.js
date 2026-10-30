document.addEventListener('click',e=>{
  const select=e.target.closest('.master-media-select'),clear=e.target.closest('.master-media-clear');
  if(!select&&!clear)return;
  const field=e.target.closest('.master-media-field'),input=field.querySelector('input'),preview=field.querySelector('img');
  const set=(id,url)=>{input.value=id;preview.src=url||''};
  if(clear){set('','');return}
  const frame=wp.media({title:'Выберите изображение',button:{text:'Использовать фото'},multiple:false,library:{type:'image'}});
  frame.on('select',()=>{const a=frame.state().get('selection').first().toJSON();set(a.id,a.sizes?.medium?.url||a.url)});frame.open();
});
