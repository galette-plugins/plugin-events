/**
 * This file is part of Galette Events plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2018-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

const path = require('path')

module.exports = {
  entry: './js/calendar.js',
  mode: 'none',
  output: {
    filename: "calendar.bundle.js",
    path: path.join(__dirname, 'webroot', 'js')
  },
  externals: {
    // shows how we can rely on browser globals instead of bundling these dependencies,
    // in case we want to access jQuery from a CDN
    jquery: 'jQuery'
  },
  devtool: 'source-map',
  module: {
    rules: [
      {
        test: /\.css$/i,
        use: [ 'style-loader', 'css-loader' ]
      }
    ]
  },
  resolve: {
    extensions: [ '.js' ],
  },
   performance: {
    hints: false,
    maxEntrypointSize: 512000,
    maxAssetSize: 512000
  }
}
