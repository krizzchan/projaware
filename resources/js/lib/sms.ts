export function estimateSmsSegments(message: string): number {
    const basic =
        '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
    const extended = '^{}\\[~]|€';
    let units = 0;
    for (const char of message) {
        if (extended.includes(char)) units += 2;
        else if (basic.includes(char)) units += 1;
        else return message.length <= 70 ? 1 : Math.ceil(message.length / 67);
    }
    return units <= 160 ? 1 : Math.ceil(units / 153);
}
