// forked from takahiro.nakamori's "JavaScriptでカレンダー" http://jsdo.it/takahiro.nakamori/tlAF
(function(){
/* ========== 休みを定義 ========== */
var holiday = [];
/* 休みの設定方法
holiday[年] = [];
holiday[年][月] = [休みの日,休みの日,休みの日,休みの日,休みの日,休みの日];
「年」「月」「休みの日」は、半角英数字で入力してください。
*/
// TODO 何年分？
holiday[2016] = [];
holiday[2016][8] = [1,2,3,4,5,11,12,13];

for(var i=2016;i<=2020;i++){
    holiday[i]=[];
    for(var k=1;k<=12;k++){
    	holiday[i][k]=[];
    }
}
var selectedDate = "16";
	
/* ========== 以下、カレンダー生成部分========== */
var yobi_ja = ['日', '月', '火', '水', '木', '金', '土'];

function TnCalendar(parent){
  if (typeof parent === 'string') {
    parent = document.getElementById(parent);
  }
  this.parent = parent;
}
    
window.TnCalendar = TnCalendar;

TnCalendar.prototype = {
  create: create,
  update: update,
  remove: remove,
  set_caption: set_caption,
  set_body: set_body,
  set_date: set_date,
  onclick_date: onclick_date,
  onclick_month: onclick_month
};

function onclick_date(id, year, month, date){
  return false;
}

function onclick_month(id, year, month){
  this.update(+year, +month);
  return false;
}
    
function remove(){
  this.parent.removeChild(this.table);
}
    
function update(year, month){
  this.remove();
  this.create(year, month);
}
    
function set_date(year, month){
  var today = new Date();
  this.month = parseInt(month, 10)|| (today.getMonth()+1);
  this.year = parseInt(year, 10) || today.getFullYear();
}
    
function set_caption(year, month){
  var today = new Date();
  var this_month = today.getMonth()+1;
  var this_year = today.getFullYear();
  
  var caption = document.createElement('caption');
  var div = document.createElement('div');
  var next = document.createElement('a');
  next.href = '#month-' + ((month == 12) ? year+1 : year)+ '-' + (month==12?1:month+1);
  next.className = 'next';
  next.innerHTML = (month==12?1:month+1) + '月 <i class="fa fa-caret-right"></i>'; 
  
  var current = document.getElementById('currentYearMonth');
  var text = document.createTextNode(year + '年' + month + '月');
  current.replaceChild(text, current.firstChild);
  
//   if(year != this_year || month != this_month){
  var prev = document.createElement('a');
  prev.href = '#month-' + ((month == 1) ? year-1 : year) + '-' + (month==1?12:month-1);
  prev.className = 'prev';
  prev.innerHTML = '<i class="fa fa-caret-left"></i> ' + (month==1?12:month-1) + '月';
  div.appendChild(prev);
//   }
  
//  div.appendChild(current);
  div.appendChild(next);
  caption.appendChild(div);
  this.table.appendChild(caption);
}
    
function set_body(year, month){
  var tbody = document.createElement('tbody');
  var first = new Date(year, month - 1, 1);
  var last = new Date(year, month, 0);
  var first_day = first.getDay();
  var last_date = last.getDate();
  var date = 1;
  var skip = true;
  for (var row = 0; row < 7; row++) {
    var tr = document.createElement('tr');
    for (var col = 0; col < 7; col++){
      if (row === 0){
        var th = document.createElement('th');
        var day = yobi_ja[col];
        th.appendChild(document.createTextNode(day));
        th.className = 'calendar day-head day' + col;
        tr.appendChild(th);
      } else {
        if (row === 1 && first_day === col){
          skip = false;
        }
        if (date > last_date) {
          skip = true;
        }
        var td = document.createElement('td');
				var selectedClass = "";
				if (date == selectedDate) {
					selectedClass = " selectedDate";
				}
        td.className = 'calendar day' + col + selectedClass;
				if (!skip) {
          td.appendChild(document.createTextNode(date));
//TODO サンプル
					if(col==6) {
						td.appendChild(document.createElement('br'));
						var scheduleP = document.createElement('span');
						scheduleP.appendChild(document.createTextNode("10:00練習@多摩川グラ…"));
						td.appendChild(scheduleP);
					}
					
    		  for(var i =0; i < holiday[year][month].length; i++){
		    	  if(holiday[year][month][i] == date){
			        td.className = td.className + ' holiday';  
			      }
		      }
          date++;
        } else {
          td.innerHTML='<span class="blank">&nbsp;</span>';
        }
        tr.appendChild(td);
      }
    }
    tbody.appendChild(tr);
  }
  this.table.appendChild(tbody);
}
    
function create(year, month){
  var that = this;
  var table = document.createElement('table');
  table.className = 'calendar-table';
  this.table = table;
  table.onclick = function(e){
    var evt = e || window.event; 
    var target = evt.target || evt.srcElement;
    return that.onclick_month.apply(that,target.hash.match(/month-(\d+)-(\d+)/));
  };
  this.set_date(year, month);
  this.set_caption(this.year, this.month);
  this.set_body(this.year, this.month);
  this.parent.appendChild(table);
}
})();
