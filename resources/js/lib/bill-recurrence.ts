import jalaali from 'jalaali-js';

const { jalaaliMonthLength, toGregorian, toJalaali } = jalaali;

function isoDate(year: number, month: number, day: number): string {
    return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function addIsoDay(value: string): string {
    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));
    date.setUTCDate(date.getUTCDate() + 1);

    return isoDate(
        date.getUTCFullYear(),
        date.getUTCMonth() + 1,
        date.getUTCDate(),
    );
}

export function nextMonthlyDueDate(
    dueDay: number,
    calendar: string,
    searchFrom: string,
): string {
    const [year, month, day] = searchFrom.split('-').map(Number);

    if (calendar === 'jalali') {
        const current = toJalaali(year, month, day);
        let jalaliYear = current.jy;
        let jalaliMonth = current.jm;

        const candidateFor = (): string => {
            const gregorian = toGregorian(
                jalaliYear,
                jalaliMonth,
                Math.min(dueDay, jalaaliMonthLength(jalaliYear, jalaliMonth)),
            );

            return isoDate(gregorian.gy, gregorian.gm, gregorian.gd);
        };

        let candidate = candidateFor();

        if (candidate < searchFrom) {
            if (jalaliMonth === 12) {
                jalaliYear++;
                jalaliMonth = 1;
            } else {
                jalaliMonth++;
            }

            candidate = candidateFor();
        }

        return candidate;
    }

    let candidateYear = year;
    let candidateMonth = month;
    const candidateFor = (): string => {
        const daysInMonth = new Date(
            Date.UTC(candidateYear, candidateMonth, 0),
        ).getUTCDate();

        return isoDate(
            candidateYear,
            candidateMonth,
            Math.min(dueDay, daysInMonth),
        );
    };

    let candidate = candidateFor();

    if (candidate < searchFrom) {
        if (candidateMonth === 12) {
            candidateYear++;
            candidateMonth = 1;
        } else {
            candidateMonth++;
        }

        candidate = candidateFor();
    }

    return candidate;
}

export function countMonthlyPaymentsThrough(
    dueDay: number,
    calendar: string,
    searchFrom: string,
    endDate: string,
): number {
    let count = 0;
    let after = searchFrom;

    while (count <= 600) {
        const dueDate = nextMonthlyDueDate(dueDay, calendar, after);

        if (dueDate > endDate) {
            break;
        }

        count++;
        after = addIsoDay(dueDate);
    }

    return count;
}
