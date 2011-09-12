var i = j = 0;
cont = true;
while (cont == true) {
	var element0 = document.getElementById('card' + i);	
	var element1 = document.getElementById('card' + j);	
	console.log(j);
	if (element0 == null) {
		cont = false;
	}
	if (element1 == null) {
		console.log('Card element is not found. Check element id.');
		i++;
		j = i;
	} else {
		var myevent = document.createEvent('MouseEvents');
		myevent.initEvent('click', false, true);
		element0.dispatchEvent(myevent);
		element1.dispatchEvent(myevent);
		console.log('Card color is "' + element1.style.backgroundColor + '".');
	}
	j++;
}
