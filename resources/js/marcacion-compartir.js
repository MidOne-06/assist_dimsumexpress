const CANVAS_WIDTH = 1080;
const CANVAS_HEIGHT = 1350;

const ACCENT_COLORS = {
    success: '#4ade80',
    warning: '#fbbf24',
    info: '#60a5fa',
    danger: '#fb4b4b',
};

const loadImage = (source) => new Promise((resolve) => {
    if (! source) {
        resolve(null);
        return;
    }

    const image = new Image();
    image.decoding = 'async';
    image.onload = () => resolve(image);
    image.onerror = () => resolve(null);
    image.src = source;
});

const drawCover = (context, image, width, height) => {
    const scale = Math.max(width / image.width, height / image.height);
    const renderedWidth = image.width * scale;
    const renderedHeight = image.height * scale;

    context.drawImage(image, (width - renderedWidth) / 2, (height - renderedHeight) / 2, renderedWidth, renderedHeight);
};

const drawCenteredLines = (context, value, centerX, startY, maxWidth, lineHeight) => {
    const words = value.trim().split(/\s+/);
    const lines = [];
    let line = '';

    words.forEach((word) => {
        const candidate = line ? `${line} ${word}` : word;
        if (line && context.measureText(candidate).width > maxWidth) {
            lines.push(line);
            line = word;
            return;
        }

        line = candidate;
    });

    if (line) lines.push(line);
    lines.forEach((text, index) => context.fillText(text, centerX, startY + (index * lineHeight)));

    return lines.length;
};

const roundedRectangle = (context, x, y, width, height, radius) => {
    const safeRadius = Math.min(radius, width / 2, height / 2);

    context.beginPath();
    context.moveTo(x + safeRadius, y);
    context.lineTo(x + width - safeRadius, y);
    context.quadraticCurveTo(x + width, y, x + width, y + safeRadius);
    context.lineTo(x + width, y + height - safeRadius);
    context.quadraticCurveTo(x + width, y + height, x + width - safeRadius, y + height);
    context.lineTo(x + safeRadius, y + height);
    context.quadraticCurveTo(x, y + height, x, y + height - safeRadius);
    context.lineTo(x, y + safeRadius);
    context.quadraticCurveTo(x, y, x + safeRadius, y);
    context.closePath();
};

const renderShareImage = async (data) => {
    const canvas = document.createElement('canvas');
    canvas.width = CANVAS_WIDTH;
    canvas.height = CANVAS_HEIGHT;
    const context = canvas.getContext('2d');

    if (! context) throw new Error('Canvas no disponible');

    const [background, logo] = await Promise.all([loadImage(data.background), loadImage(data.logo)]);
    const accent = ACCENT_COLORS[data.color] ?? ACCENT_COLORS.success;
    const centerX = CANVAS_WIDTH / 2;

    const gradient = context.createLinearGradient(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
    gradient.addColorStop(0, '#07172b');
    gradient.addColorStop(1, '#020a16');
    context.fillStyle = gradient;
    context.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);

    if (background) {
        context.save();
        context.globalAlpha = 0.24;
        drawCover(context, background, CANVAS_WIDTH, CANVAS_HEIGHT);
        context.restore();
    }

    const shade = context.createLinearGradient(0, 0, 0, CANVAS_HEIGHT);
    shade.addColorStop(0, 'rgba(2, 11, 24, .35)');
    shade.addColorStop(1, 'rgba(2, 9, 20, .72)');
    context.fillStyle = shade;
    context.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);

    if (logo) {
        const logoHeight = 170;
        const logoWidth = Math.min(440, (logo.width / logo.height) * logoHeight);
        context.drawImage(logo, centerX - (logoWidth / 2), 88, logoWidth, logoHeight);
    } else {
        context.fillStyle = '#ffffff';
        context.font = '700 46px Arial, sans-serif';
        context.textAlign = 'center';
        context.fillText('DIM SUM EXPRESS', centerX, 180);
    }

    context.strokeStyle = '#e11d48';
    context.lineWidth = 3;
    context.beginPath();
    context.moveTo(84, 305);
    context.lineTo(360, 305);
    context.moveTo(720, 305);
    context.lineTo(996, 305);
    context.stroke();
    context.fillStyle = '#d9e7ff';
    context.font = '700 28px Arial, sans-serif';
    context.letterSpacing = '6px';
    context.textAlign = 'center';
    context.fillText('MARCACIÓN', centerX, 315);
    context.letterSpacing = '0px';

    const panelY = 370;
    const panelHeight = 750;
    roundedRectangle(context, 75, panelY, 930, panelHeight, 46);
    context.fillStyle = 'rgba(8, 29, 56, .79)';
    context.fill();
    context.strokeStyle = 'rgba(147, 197, 253, .74)';
    context.lineWidth = 2;
    context.stroke();

    context.fillStyle = accent;
    context.beginPath();
    context.arc(centerX, 525, 68, 0, Math.PI * 2);
    context.fill();
    context.strokeStyle = '#08203d';
    context.lineWidth = 14;
    context.lineCap = 'round';
    context.beginPath();
    context.moveTo(505, 526);
    context.lineTo(535, 556);
    context.lineTo(595, 490);
    context.stroke();

    context.fillStyle = '#f8fafc';
    context.font = '700 50px Arial, sans-serif';
    context.textAlign = 'center';
    const eventLines = drawCenteredLines(context, data.event, centerX, 675, 760, 60);

    context.fillStyle = '#dbeafe';
    context.font = '500 42px Arial, sans-serif';
    const nameStart = 745 + ((eventLines - 1) * 60);
    const nameLines = drawCenteredLines(context, data.name, centerX, nameStart, 790, 52);

    const timeY = nameStart + (nameLines * 52) + 85;
    context.fillStyle = accent;
    context.font = '700 108px Arial, sans-serif';
    context.fillText(data.time, centerX, timeY);
    context.fillStyle = '#cbd5e1';
    context.font = '500 38px Arial, sans-serif';
    context.fillText(data.date, centerX, timeY + 62);

    context.strokeStyle = 'rgba(191, 219, 254, .35)';
    context.lineWidth = 2;
    context.beginPath();
    context.moveTo(170, 1040);
    context.lineTo(910, 1040);
    context.stroke();
    context.fillStyle = '#dbeafe';
    context.font = '700 24px Arial, sans-serif';
    context.fillText('DIM SUM EXPRESS', centerX, 1230);

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
    if (! blob) throw new Error('No se pudo generar la imagen');

    return blob;
};

const descargar = (blob) => {
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'marcacion-dim-sum.png';
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(link.href), 1000);
};

const puedeCompartirArchivo = (shareData) => {
    if (! shareData || typeof navigator.share !== 'function') return false;
    if (typeof navigator.canShare !== 'function') return true;

    try {
        return navigator.canShare({ files: shareData.files });
    } catch (_) {
        // Algunas versiones de Safari exponen canShare(), pero rechazan el
        // formato de archivos. En ese caso se usa la descarga como respaldo.
        return false;
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const button = document.querySelector('[data-marcacion-share]');
    const feedback = document.querySelector('[data-marcacion-share-feedback]');
    if (! button) return;

    const setFeedback = (message) => {
        if (! feedback) return;
        feedback.textContent = message;
        feedback.hidden = ! message;
    };

    button.addEventListener('click', async () => {
        if (button.disabled) return;

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        setFeedback('Preparando imagen…');

        try {
            const blob = await renderShareImage({
                name: button.dataset.marcacionName ?? '',
                event: button.dataset.marcacionEvent ?? '',
                time: button.dataset.marcacionTime ?? '',
                date: button.dataset.marcacionDate ?? '',
                color: button.dataset.marcacionColor ?? 'success',
                logo: button.dataset.marcacionLogo ?? '',
                background: button.dataset.marcacionBackground ?? '',
            });
            const file = typeof File === 'function'
                ? new File([blob], 'marcacion-dim-sum.png', { type: 'image/png' })
                : null;
            const shareData = file ? { files: [file], title: 'Dim Sum Express' } : null;
            const canShareFile = puedeCompartirArchivo(shareData);

            if (canShareFile) {
                try {
                    await navigator.share(shareData);
                    setFeedback('');
                    return;
                } catch (error) {
                    if (error?.name === 'AbortError') {
                        setFeedback('');
                        return;
                    }
                }
            }

            descargar(blob);
            setFeedback('La imagen se descargó. Ábrela desde tu galería para compartirla.');
        } catch (_) {
            setFeedback('No se pudo preparar la imagen. Inténtalo nuevamente.');
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    });
});
