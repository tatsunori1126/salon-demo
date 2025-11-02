import gulp from 'gulp';
import gulpSass from 'gulp-sass';
import dartSass from 'sass';
import postcss from 'gulp-postcss';
import autoprefixer from 'autoprefixer';
import cleanCSS from 'gulp-clean-css';
import uglify from 'gulp-uglify';
import image from 'gulp-image';
import browserSync from 'browser-sync';
import plumber from 'gulp-plumber';
import notify from 'gulp-notify';
import newer from 'gulp-newer';
import rename from 'gulp-rename';

const { src, dest, watch, series, parallel } = gulp;

const sass = gulpSass(dartSass);

// Paths
const paths = {
  styles: {
    src: './scss/**/*.scss',
    dest: './css/'
  },
  scripts: {
    src: ['./js/**/*.js', '!./js/**/*.min.js'], // 修正: .min.jsを除外
    dest: './js/' // 元のディレクトリに出力
  },
  images: {
    src: ['./images/**/*.{jpg,jpeg,png,svg,gif}', '!./images/min/**/*'], // minディレクトリを除外
    dest: './images/min/'
  },
  php: {
    src: './**/*.php'
  }
};

// Compile and output unminified style.css
export function compileCSS(done) {
  return src(paths.styles.src)
    .pipe(plumber({ errorHandler: notify.onError("Sass Error: <%= error.message %>") }))
    .pipe(sass().on('error', sass.logError))
    .pipe(postcss([autoprefixer()]))
    .pipe(dest(paths.styles.dest))  // style.cssを出力
    .pipe(browserSync.stream())     // ブラウザのリロードを反映
    .on('end', done);
}

// Compile and output minified style.min.css
export function minifyCSS(done) {
  return src(paths.styles.src)
    .pipe(plumber({ errorHandler: notify.onError("Sass Error: <%= error.message %>") }))
    .pipe(sass().on('error', sass.logError))
    .pipe(postcss([autoprefixer()]))
    .pipe(cleanCSS())
    .pipe(rename({ suffix: '.min' }))  // style.min.cssとして保存
    .pipe(dest(paths.styles.dest))     // style.min.cssを出力
    .pipe(browserSync.stream())        // ブラウザのリロードを反映
    .on('end', done);
}

// Minify JavaScript and output script.min.js in the same directory
export function scripts(done) {
  return src(paths.scripts.src)
    .pipe(plumber({ errorHandler: notify.onError("Error: <%= error.message %>") }))
    .pipe(uglify())
    .pipe(rename({ suffix: '.min' })) // script.min.jsとして保存
    .pipe(dest(paths.scripts.dest))   // 元のディレクトリに出力
    .pipe(browserSync.stream())
    .on('end', done);
}

// Optimize Images
export function images(done) {
  return src(paths.images.src)
    .pipe(newer(paths.images.dest))
    .pipe(image())
    .pipe(dest(paths.images.dest))
    .on('end', done);
}

// Browser Sync
export function serve() {
  browserSync.init({
    proxy: "http://salon-demo.local/"
  });

  watch(paths.styles.src, series(compileCSS, minifyCSS)); // SCSS変更時に両方の処理を実行
  watch(paths.scripts.src, scripts);
  watch(paths.images.src, images);
  watch(paths.php.src).on('change', browserSync.reload);
}

export default series(
  parallel(compileCSS, minifyCSS, scripts, images),
  serve
);
