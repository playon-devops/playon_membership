const express = require('express');
const cors = require('cors');
const path = require('path');
const db = require('./db');
const multer = require('multer');
const QRCode = require('qrcode');
const fs = require('fs');

const app = express();
const PORT = 3000;

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static(path.join(__dirname, '..', 'public')));
app.use('/uploads', express.static(path.join(__dirname, '..', 'public', 'uploads')));

// Upload Setup
const storage = multer.diskStorage({
    destination: function (req, file, cb) {
        const dir = path.join(__dirname, '..', 'public', 'uploads');
        if (!fs.existsSync(dir)) {
            fs.mkdirSync(dir, { recursive: true });
        }
        cb(null, dir);
    },
    filename: function (req, file, cb) {
        const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
        cb(null, file.fieldname + '-' + uniqueSuffix + path.extname(file.originalname));
    }
});
const upload = multer({ storage: storage });

// --- ROUTES ---

// Helper to get session info
function getCurrentSession() {
    const now = new Date();
    const day = now.getDay(); // 0=Sun, 1=Mon, ..., 6=Sat
    const hour = now.getHours();
    const minute = now.getMinutes();
    const timeVal = hour * 60 + minute;

    // Tue-Fri (2,3,4,5)
    if (day >= 2 && day <= 5) {
        if (timeVal >= 14 * 60 && timeVal <= 16 * 60 + 30) return "Tue-Fri Session 1 (2:00pm - 4:30pm)";
        if (timeVal >= 17 * 60 && timeVal <= 20 * 60) return "Tue-Fri Session 2 (5:00pm - 8:00pm)";
    }
    // Sat(6), Sun(0) (Assuming Holidays treated as Sat/Sun logic for now)
    else if (day === 0 || day === 6) {
        if (timeVal >= 11 * 60 && timeVal <= 13 * 60) return "Weekend Session 1 (11:00am - 1:00pm)";
        if (timeVal >= 14 * 60 && timeVal <= 16 * 60) return "Weekend Session 2 (2:00pm - 4:00pm)";
        if (timeVal >= 16 * 60 + 30 && timeVal <= 18 * 60 + 30) return "Weekend Session 3 (4:30pm - 6:30pm)";
        if (timeVal >= 19 * 60 && timeVal <= 21 * 60) return "Weekend Session 4 (7:00pm - 9:00pm)";
    }

    return null; // No active session
}

// REGISTER PARENT + KIDS
app.post('/api/register', upload.any(), async (req, res) => {
    try {
        const { parentName, parentPhone } = req.body;
        // Logic to extract kids data might be complex depending on form data structure.
        // Expecting keys like kidName_0, kidDob_0 to parse list.

        // Transaction
        const insertParent = db.prepare('INSERT INTO parents (name, phone, photo_path) VALUES (?, ?, ?)');
        const insertKid = db.prepare('INSERT INTO kids (parent_id, name, dob, photo_path, qr_code_data, membership_expiry) VALUES (?, ?, ?, ?, ?, ?)');

        const transaction = db.transaction(() => {
            // Find parent photo
            const parentPhotoFile = req.files.find(f => f.fieldname === 'parentPhoto');
            const parentPhotoPath = parentPhotoFile ? '/uploads/' + parentPhotoFile.filename : null;

            let parent;
            try {
                const info = insertParent.run(parentName, parentPhone, parentPhotoPath);
                parent = { id: info.lastInsertRowid };
            } catch (err) {
                if (err.code === 'SQLITE_CONSTRAINT_UNIQUE') {
                    // Check if parent exists
                    parent = db.prepare('SELECT id FROM parents WHERE phone = ?').get(parentPhone);
                    if (!parent) throw err; // Should not happen
                } else {
                    throw err;
                }
            }

            // Process kids
            // We iterate strictly looking for kidName_{index}
            let i = 0;
            const kidsAdded = [];
            while (req.body[`kidName_${i}`]) {
                const kName = req.body[`kidName_${i}`];
                const kDob = req.body[`kidDob_${i}`];
                const kPhotoFile = req.files.find(f => f.fieldname === `kidPhoto_${i}`);
                const kPhotoPath = kPhotoFile ? '/uploads/' + kPhotoFile.filename : null;

                // QR Data: Unique string. UUID or similar. Let's use `PO-${Date.now()}-${parent.id}-${i}`
                const qrData = `PO-${Date.now()}-${parent.id}-${i}`;

                // Expiry: 30 days from now
                const expiry = new Date();
                expiry.setDate(expiry.getDate() + 30);

                const kInfo = insertKid.run(parent.id, kName, kDob, kPhotoPath, qrData, expiry.toISOString());
                kidsAdded.push({ id: kInfo.lastInsertRowid, name: kName, qrData });
                i++;
            }
            return { parentId: parent.id, kids: kidsAdded };
        });

        const result = transaction();

        // Generate QR Codes for response (so frontend can print)
        for (let k of result.kids) {
            k.qrDataUrl = await QRCode.toDataURL(k.qrData);
        }

        res.json({ success: true, data: result });
    } catch (err) {
        console.error(err);
        res.status(500).json({ success: false, error: err.message });
    }
});

// SEARCH MEMBERS
app.get('/api/members', (req, res) => {
    const q = req.query.q;
    if (!q) return res.json([]);

    // Search parents or kids
    const searchParents = db.prepare('SELECT * FROM parents WHERE name LIKE ? OR phone LIKE ?').all(`%${q}%`, `%${q}%`);
    const searchKids = db.prepare(`
        SELECT k.*, p.name as parent_name, p.phone as parent_phone 
        FROM kids k 
        JOIN parents p ON k.parent_id = p.id 
        WHERE k.name LIKE ? OR k.qr_code_data = ?
    `).all(`%${q}%`, q);

    res.json({ parents: searchParents, kids: searchKids });
});

// GET MEMBER DETAILS (for card/checkin)
app.get('/api/kid/:id', async (req, res) => {
    const kid = db.prepare(`
        SELECT k.*, p.name as parent_name, p.phone as parent_phone, p.photo_path as parent_photo 
        FROM kids k 
        JOIN parents p ON k.parent_id = p.id 
        WHERE k.id = ?
    `).get(req.params.id);

    if (kid) {
        kid.qrDataUrl = await QRCode.toDataURL(kid.qr_code_data);
    }

    // Get visits
    const visits = db.prepare('SELECT * FROM visits WHERE kid_id = ? ORDER BY timestamp DESC LIMIT 50').all(req.params.id);

    res.json({ kid, visits });
});

// CHECK IN
app.post('/api/checkin', (req, res) => {
    const { qrCode } = req.body;

    const kid = db.prepare('SELECT * FROM kids WHERE qr_code_data = ?').get(qrCode);
    if (!kid) return res.status(404).json({ success: false, message: 'Invalid QR Code' });

    // 1. Check Membership Expiry
    if (new Date(kid.membership_expiry) < new Date()) {
        return res.status(400).json({ success: false, message: 'Membership Expired' });
    }

    // 2. Check Session
    const session = getCurrentSession();
    if (!session) {
        return res.status(400).json({ success: false, message: 'No active session currently.' });
    }

    // 3. Check if already checked in TODAY
    const startOfDay = new Date();
    startOfDay.setHours(0, 0, 0, 0);

    const existingVisit = db.prepare('SELECT * FROM visits WHERE kid_id = ? AND timestamp >= ?').get(kid.id, startOfDay.toISOString());

    if (existingVisit) {
        return res.status(400).json({ success: false, message: `Already checked in today for ${existingVisit.session_name} at ${new Date(existingVisit.timestamp).toLocaleTimeString()}` });
    }

    // Record Visit
    db.prepare('INSERT INTO visits (kid_id, session_name) VALUES (?, ?)').run(kid.id, session);

    // Return updated info
    const visits = db.prepare('SELECT * FROM visits WHERE kid_id = ? ORDER BY timestamp DESC').all(kid.id);

    res.json({ success: true, message: `Checked in for ${session}`, kid, visitCount: visits.length });
});

// REPORTS
app.get('/api/reports', (req, res) => {
    const totalKids = db.prepare('SELECT COUNT(*) as count FROM kids').get().count;
    const todayVisits = db.prepare("SELECT COUNT(*) as count FROM visits WHERE timestamp >= date('now', 'start of day')").get().count;

    res.json({ totalKids, todayVisits });
});

app.listen(PORT, () => {
    console.log(`Server running on http://localhost:${PORT}`);
});
