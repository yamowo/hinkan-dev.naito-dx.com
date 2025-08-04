const path  = require('path');

module.exports = {
  mode: 'production',
  context: path.join(__dirname, "/src/js"),
  entry: {
    main: "./main.js",
  },
  output: {
    path: path.join(__dirname, "/dev/js"),
    filename: "[name].min.js"  // [name] はエントリ名
  },
  watch: true,
  watchOptions: {
    ignored: /node_modules/
  }
};