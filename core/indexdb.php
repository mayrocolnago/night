<?php
/* Example usage with callbacks:
    
function callbackExample() {
    const callback = function(result) { console.log(result); };
    const database = new indexdb('Images');

    database.create({"key": "value"}, callback);
    database.update({"key": "newvalue"}, "myCustomId", callback);
    database.read("myCustomId", callback);
    database.list({}, callback);
    database.delete("myCustomId", callback);
}

Example usage with Promises (async/await):

async function promiseExample() {
    const database = new indexdb('Images');

    Create with auto-increment ID
    const id = await database.create({"key": "value"});
    console.log('Created with ID:', id);

    Create with specific ID
    await database.create({"key": "value"}, "myCustomId");

    Update
    const updateResult = await database.update({"key": "newvalue"}, "myCustomId");
    console.log('Update result:', updateResult); // 1 or 0

    Read
    const record = await database.read("myCustomId");
    console.log('Record:', record);

    List all
    const allRecords = await database.list();
    console.log('All records:', allRecords);

    List with filter
    const filteredRecords = await database.list({"key": "value"});
    console.log('Filtered records:', filteredRecords);

    Delete
    const deleteResult = await database.delete("myCustomId");
    console.log('Delete result:', deleteResult); // 1 or 0
} */

class indexdb {

    public static function js() {
        ?><script>
            /* Global cache for database connections and metadata */
            window._indexDBCache = window._indexDBCache || {
              connections: {},
              metadata: {}
            };

            class indexdb {
              constructor(tableName) {
                this.dbName = tableName;
                this.tableName = 'storage';
                this.version = 1;
                this.db = null;
              }
          
              /**
               * Initialize or get cached database connection
               */
              _getConnection(callback) {
                const cacheKey = `${this.dbName}_${this.tableName}`;

                /* Return cached connection if available */
                if(window._indexDBCache.connections[cacheKey])
                  return callback(null, window._indexDBCache.connections[cacheKey]);
                
                try {
                  const request = indexedDB.open(this.dbName, this.version);
              
                  request.onerror = () => { callback(new Error('Failed to open database'), null); };
                  request.onsuccess = (event) => {
                    const db = event.target.result;
                    window._indexDBCache.connections[cacheKey] = db;
                    callback(null, db);
                  };

                  request.onupgradeneeded = (event) => {
                    const db = event.target.result;
                    /* Create object store if it doesn't exist */
                    if (!db.objectStoreNames.contains(this.tableName))
                      db.createObjectStore(this.tableName, { keyPath: 'id' });
                  };
                } catch(cerr) { callback(new Error('Failed to open database'), null); }
              }
          
              /**
               * Get next auto-increment ID for a table
               */
              _getNextId(db, callback) {
                const metaKey = `${this.dbName}_${this.tableName}_nextId`;

                /* Initialize if not exists */
                if(!window._indexDBCache.metadata[metaKey])
                  window._indexDBCache.metadata[metaKey] = 1;
            
                /* Check existing keys to find the highest numeric ID */
                try {
                  const transaction = db.transaction([this.tableName], 'readonly');
                  const store = transaction.objectStore(this.tableName);
                  const request = store.getAllKeys();
              
                  request.onsuccess = () => {
                    const keys = request.result;
                    let maxId = 0;
                    /* Find highest numeric ID */
                    keys.forEach(key => {
                      const numKey = parseInt(key);
                      if (!isNaN(numKey) && numKey > maxId) maxId = numKey;
                    });
                    /* Update cache to be one more than max */
                    window._indexDBCache.metadata[metaKey] = maxId + 1;
                    callback(null, window._indexDBCache.metadata[metaKey]);
                  };
                  request.onerror = () => { callback(new Error('Failed to get next ID'), null); };
                } catch(cerr) { callback(new Error('Failed to get next ID'), null, cerr); }
              }
          
              /**
               * Create a new row
               * Returns Promise if no callback, otherwise uses callback
               */
              create(data, id, callback) {
                /* Handle optional id parameter */
                if(typeof id === 'function') { callback = id; id = null; }

                try {
                    data.updated_at = null;
                    data.created_at = new Date().toISOString();
                } catch(cerr) { }
            
                /* If no callback, return Promise */
                if(!callback)
                  return new Promise((resolve, reject) => {
                    this.create(data, id, (result) => {
                      if(result && result.error) reject(new Error(result.error));
                      else resolve(result);
                    });
                  });
            
                /* Callback version */
                this._getConnection((err, db) => {
                  if(err) return callback(0);
              
                  const executeCreate = (finalId) => {
                    try {
                      const transaction = db.transaction([this.tableName], 'readwrite');
                      const store = transaction.objectStore(this.tableName);

                      const record = { ...data, id: finalId };
                      const request = store.add(record);
                  
                      request.onsuccess = () => { callback(finalId); };                  
                      request.onerror = () => { callback(0, 'request Error'); };
                    } catch(cerr) { callback(0, 'Connection error', cerr); }
                  };
              
                  /* If ID provided, use it; otherwise get next auto-increment ID */
                  if(id !== null && id !== undefined) {
                    id = String(id).replace(/[^0-9a-zA-Z\\\/\_\-]/gi,'');
                    executeCreate(id);
                  } else {
                    this._getNextId(db, (err, nextId, cerrh) => {
                      if(err) return callback(0, 'Couldnt get next id', err, cerrh);
                      
                      executeCreate(nextId);
                      /* Increment for next use */
                      const metaKey = `${this.dbName}_${this.tableName}_nextId`;
                      window._indexDBCache.metadata[metaKey]++;
                    });
                  }
                });
              }
          
              /**
               * Update an existing row
               * Returns Promise if no callback, otherwise uses callback
               */
              update(data, id, callback) {
                /* If no callback, return Promise */
                if(!callback)
                  return new Promise((resolve) => { this.update(data, id, (result) => { resolve(result); }); });

                try { data.updated_at = new Date().toISOString(); } catch(cerr) { }
            
                /* Callback version */
                if(!id) return callback(0);
                id = String(id).replace(/[^0-9a-zA-Z\\\/\_\-]/gi,'');
            
                this._getConnection((err, db) => {
                  if(err) return callback(0);
                  try {
                    const transaction = db.transaction([this.tableName], 'readwrite');
                    const store = transaction.objectStore(this.tableName);

                    /* First check if record exists */
                    const getRequest = store.get(id);
                
                    getRequest.onsuccess = () => {
                      if(!getRequest.result) return callback(0);

                      /* Merge existing data with new data */
                      const record = { ...getRequest.result, ...data, id: id };
                      const putRequest = store.put(record);
                  
                      putRequest.onsuccess = () => { callback(1); };
                      putRequest.onerror = () => { callback(0); };
                    };
                    getRequest.onerror = () => { callback(0); };
                  } catch(cerr) { callback(0); }
                });
              }
          
              /**
               * Delete a row
               * Returns Promise if no callback, otherwise uses callback
               */
              delete(id, callback) {
                /* If no callback, return Promise */
                if(!callback)
                  return new Promise((resolve) => { this.delete(id, (result) => { resolve(result); }); });
            
                /* Callback version */
                if(!id) return callback(0);
            
                this._getConnection((err, db) => {
                  if(err) return callback(0);
                  try {
                    const transaction = db.transaction([this.tableName], 'readwrite');
                    const store = transaction.objectStore(this.tableName);
                    const request = store.delete(id);
                
                    request.onsuccess = () => { callback(1); };
                    request.onerror = () => { callback(0); };
                  } catch(cerr) { callback(0); }
                });
              }
          
              /**
               * Read a single row
               * Returns Promise if no callback, otherwise uses callback
               */
              read(id, callback) {
                /* If no callback, return Promise */
                if(!callback)
                  return new Promise((resolve) => { this.read(id, (result) => { resolve(result); }); });
            
                /* Callback version */
                if(!id) return callback(null);
            
                this._getConnection((err, db) => {
                  if(err) returncallback(null);
                  try {
                    const transaction = db.transaction([this.tableName], 'readonly');
                    const store = transaction.objectStore(this.tableName);
                    const request = store.get(id);
                
                    request.onsuccess = () => { callback(request.result || {}); };
                    request.onerror = () => { callback({}); };
                  } catch(cerr) { callback({}); }
                });
              }
          
              /**
               * List all rows, optionally filtered by search criteria
               * Returns Promise if no callback, otherwise uses callback
               */
              list(searchCriteria, callback) {
                /* Handle optional searchCriteria parameter */
                if (typeof searchCriteria === 'function') {
                  callback = searchCriteria;
                  searchCriteria = {};
                }
            
                /* If no callback, return Promise */
                if(!callback)
                  return new Promise((resolve) => { this.list(searchCriteria, (result) => { resolve(result); }); });
            
                /* Callback version */
                this._getConnection((err, db) => {
                  if(err) return callback({});
                  try {
                    const transaction = db.transaction([this.tableName], 'readonly');
                    const store = transaction.objectStore(this.tableName);
                    const request = store.getAll();
                
                    request.onsuccess = () => {
                      const allRecords = request.result;
                      const result = {};
                      /* Filter records based on search criteria */
                      allRecords.forEach(record => {
                        let matches = true;
                        /* If search criteria provided, check if record matches */
                        if(searchCriteria && Object.keys(searchCriteria).length > 0)
                          for(let key in searchCriteria)
                            if(record[key] !== searchCriteria[key]) {
                              matches = false;
                              break;
                            }
                        if(matches) result[record.id] = record;
                      });
                      callback(result);
                    };
                    request.onerror = () => { callback({}); };
                  } catch(cerr) { callback({}); }
                });
              }
            }
        </script><?php
    }

}