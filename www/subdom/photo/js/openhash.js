function openHash(callback){
	var hash = window.location.hash;
	if (hash) callback(hash);
}