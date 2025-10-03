function $(sel, el=document){return el.querySelector(sel)}
function $all(sel, el=document){return Array.from(el.querySelectorAll(sel))}
function saveToken(token){localStorage.setItem('token', token)}
function getToken(){return localStorage.getItem('token')}
function authHeader(){const t=getToken();return t?{'Authorization':'Bearer '+t}:{}}
function apiBase(){return location.origin}
async function request(path, opts={}){
  const headers=Object.assign({'Content-Type':'application/json'}, authHeader(), opts.headers||{})
  const res = await fetch(apiBase()+path, {method: opts.method||'GET', headers, body: opts.body?JSON.stringify(opts.body):undefined})
  const text = await res.text();
  let json; try{ json = text?JSON.parse(text):{} } catch(e){ json = {raw:text} }
  if(!res.ok) throw {status:res.status, data:json}
  return json
}
function toast(msg){alert(msg)}
